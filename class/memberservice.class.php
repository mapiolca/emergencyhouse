<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/adherents/class/adherent.class.php';
require_once DOL_DOCUMENT_ROOT.'/adherents/class/adherent_type.class.php';

dol_include_once('/emergencyhouse/class/publicaccount.class.php');

/**
 * Native Dolibarr member integration for public accounts.
 */
class EmergencyHouseMemberService
{
	/** @var DoliDB */
	private $db;
	/** @var string */
	public $error = '';
	/** @var array<int, string> */
	public $errors = array();
	/** @var string */
	public $lastOperation = '';

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Check that the native member integration is ready for the current entity.
	 *
	 * @param int $entity Entity
	 * @return bool
	 */
	public function isReady($entity)
	{
		$this->error = '';
		if (!isModEnabled('member')) {
			$this->error = 'ErrorMemberModuleUnavailable';
			return false;
		}

		$typeId = getDolGlobalInt('EMERGENCYHOUSE_ADHERENT_TYPE_ID', 0);
		if ($typeId <= 0) {
			$this->error = 'ErrorMemberTypeNotConfigured';
			return false;
		}

		return $this->fetchUsableMemberType($typeId, $entity) instanceof AdherentType;
	}

	/**
	 * Return active member types that accept natural persons.
	 *
	 * @param int $entity Entity
	 * @return array<int, string>
	 */
	public function getAvailableMemberTypes($entity)
	{
		if (!isModEnabled('member') || $entity <= 0) {
			return array();
		}

		$sql = 'SELECT rowid, libelle FROM '.MAIN_DB_PREFIX.'adherent_type';
		$sql .= ' WHERE entity IN ('.$this->db->sanitize(getEntity('member_type')).')';
		$sql .= ' AND statut = 1';
		$sql .= " AND (morphy IS NULL OR morphy = '' OR morphy = 'phy')";
		$sql .= ' ORDER BY libelle ASC, rowid ASC';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return array();
		}

		$types = array();
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$types[(int) $obj->rowid] = (string) $obj->libelle;
		}

		return $types;
	}

	/**
	 * Load and validate one configured native member type.
	 *
	 * @param int $typeId Member type ID
	 * @param int $entity Current entity
	 * @return AdherentType|false
	 */
	public function fetchUsableMemberType($typeId, $entity)
	{
		$this->error = '';
		if (!isModEnabled('member')) {
			$this->error = 'ErrorMemberModuleUnavailable';
			return false;
		}
		if ($typeId <= 0 || $entity <= 0) {
			$this->error = 'ErrorMemberTypeNotConfigured';
			return false;
		}

		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'adherent_type';
		$sql .= ' WHERE rowid = '.((int) $typeId);
		$sql .= ' AND entity IN ('.$this->db->sanitize(getEntity('member_type')).')';
		$sql .= ' AND statut = 1';
		$sql .= " AND (morphy IS NULL OR morphy = '' OR morphy = 'phy')";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}
		if ($this->db->num_rows($resql) !== 1) {
			$this->error = 'ErrorMemberTypeUnavailable';
			return false;
		}

		$type = new AdherentType($this->db);
		if ($type->fetch($typeId) <= 0 || (int) $type->status !== 1 || !in_array((string) $type->morphy, array('', 'phy'), true)) {
			$this->error = 'ErrorMemberTypeUnavailable';
			return false;
		}

		return $type;
	}

	/**
	 * Create, validate or link the native member for one public account.
	 *
	 * This method owns a transaction level. It can safely run inside the wider
	 * public registration transaction because DoliDB nests transaction levels.
	 *
	 * @param EmergencyHousePublicAccount $account Public account
	 * @param User                        $user Trigger actor
	 * @return int Native member ID, or a negative value
	 */
	public function provisionForAccount($account, $user)
	{
		global $conf;

		$this->error = '';
		$this->errors = array();
		$this->lastOperation = '';
		if ($account->id <= 0 || (int) $account->entity !== (int) $conf->entity) {
			$this->error = 'ErrorMemberAccountEntityMismatch';
			return -1;
		}
		if (!$this->isReady((int) $account->entity)) {
			return -1;
		}

		$this->db->begin();
		if ($account->fk_member > 0) {
			$linkedMember = $this->fetchMemberForAccount($account);
			if (!$linkedMember instanceof Adherent) {
				$this->db->rollback();
				if ($this->error === '') {
					$this->error = 'ErrorMemberLinkInvalid';
				}
				return -1;
			}
			if ((int) $linkedMember->statut === Adherent::STATUS_DRAFT) {
				if ($linkedMember->validate($user) < 0) {
					$this->db->rollback();
					$this->error = 'ErrorMemberValidation';
					return -1;
				}
				$this->lastOperation = 'linked_validated';
			} elseif ((int) $linkedMember->statut === Adherent::STATUS_VALIDATED) {
				$this->lastOperation = 'already_linked';
			} else {
				$this->db->rollback();
				$this->error = 'ErrorMemberStatusConflict';
				return -1;
			}
			$this->db->commit();
			return (int) $linkedMember->id;
		}

		$profile = $account->getDecryptedProfile();
		if (!is_array($profile)) {
			$this->db->rollback();
			$this->error = 'ErrorMemberProfileUnavailable';
			return -1;
		}
		if (!$this->profileFitsNativeMember($profile)) {
			$this->db->rollback();
			return -1;
		}

		$matches = $this->findMembersByEmail((int) $account->entity, $profile['email']);
		if (!is_array($matches)) {
			$this->db->rollback();
			return -1;
		}
		if (count($matches) > 1) {
			$this->db->rollback();
			$this->error = 'ErrorMemberMultipleMatches';
			return -1;
		}

		if (count($matches) === 1) {
			$match = $matches[0];
			if ((string) $match->morphy !== 'phy') {
				$this->db->rollback();
				$this->error = 'ErrorMemberNatureConflict';
				return -1;
			}
			$member = $this->fetchMemberInEntity((int) $match->rowid, (int) $account->entity);
			if (!$member instanceof Adherent) {
				$this->db->rollback();
				return -1;
			}
			if ((int) $member->statut === Adherent::STATUS_DRAFT) {
				if ($member->validate($user) < 0) {
					$this->db->rollback();
					$this->error = 'ErrorMemberValidation';
					return -1;
				}
				$this->lastOperation = 'linked_validated';
			} elseif ((int) $member->statut === Adherent::STATUS_VALIDATED) {
				$this->lastOperation = 'linked';
			} else {
				$this->db->rollback();
				$this->error = 'ErrorMemberStatusConflict';
				return -1;
			}
		} else {
			$typeId = getDolGlobalInt('EMERGENCYHOUSE_ADHERENT_TYPE_ID', 0);
			$type = $this->fetchUsableMemberType($typeId, (int) $account->entity);
			if (!$type instanceof AdherentType) {
				$this->db->rollback();
				return -1;
			}

			$member = new Adherent($this->db);
			$member->entity = (int) $account->entity;
			$member->typeid = (int) $type->id;
			$member->morphy = 'phy';
			$member->firstname = $profile['firstname'];
			$member->lastname = $profile['lastname'];
			$member->email = $profile['email'];
			$member->phone = $profile['phone'];
			$member->login = 'eh_'.$account->public_uuid;
			$member->default_lang = $account->lang;
			$member->public = 0;
			if ($member->create($user) <= 0) {
				$this->db->rollback();
				$this->error = 'ErrorMemberCreation';
				return -1;
			}
			if ($member->validate($user) < 0) {
				$this->db->rollback();
				$this->error = 'ErrorMemberValidation';
				return -1;
			}
			$this->lastOperation = 'created';
		}

		if ($account->linkMember((int) $member->id) <= 0) {
			$this->db->rollback();
			$this->error = $account->error !== '' ? $account->error : 'ErrorMemberLink';
			return -1;
		}

		$this->db->commit();
		return (int) $member->id;
	}

	/**
	 * Reconcile verified active accounts in bounded, restartable batches.
	 *
	 * @param int  $entity  Entity
	 * @param User $user    Trigger actor
	 * @param int  $limit   Batch size
	 * @param int  $afterId Resume after this account ID
	 * @return array{processed:int,created:int,linked:int,validated:int,skipped:int,conflicts:int,errors:int,remaining:int,next_id:int}
	 */
	public function reconcileVerifiedAccounts($entity, $user, $limit = 100, $afterId = 0)
	{
		$result = array(
			'processed' => 0,
			'created' => 0,
			'linked' => 0,
			'validated' => 0,
			'skipped' => 0,
			'conflicts' => 0,
			'errors' => 0,
			'remaining' => 0,
			'next_id' => 0,
		);
		$limit = max(1, min(500, $limit));
		$afterId = max(0, $afterId);
		if (!$this->isReady($entity)) {
			$result['errors'] = 1;
			$result['remaining'] = $this->countAccountsToReconcile($entity);
			return $result;
		}

		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'emergencyhouse_public_account';
		$sql .= ' WHERE entity = '.((int) $entity);
		$sql .= ' AND status = '.EmergencyHousePublicAccount::STATUS_ACTIVE;
		$sql .= ' AND email_verified = 1 AND fk_member IS NULL';
		$sql .= ' AND rowid > '.((int) $afterId);
		$sql .= ' ORDER BY rowid ASC';
		$sql .= $this->db->plimit($limit);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$result['errors'] = 1;
			$result['remaining'] = $this->countAccountsToReconcile($entity);
			return $result;
		}

		$lastId = 0;
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$lastId = (int) $obj->rowid;
			$result['processed']++;
			$account = new EmergencyHousePublicAccount($this->db);
			if ($account->fetch($lastId, $entity) <= 0) {
				$result['errors']++;
				continue;
			}
			if ($this->provisionForAccount($account, $user) <= 0) {
				if (in_array($this->error, array(
					'ErrorMemberLinkConflict',
					'ErrorMemberMultipleMatches',
					'ErrorMemberNatureConflict',
					'ErrorMemberStatusConflict',
				), true)) {
					$result['conflicts']++;
				} else {
					$result['errors']++;
				}
				continue;
			}

			if ($this->lastOperation === 'created') {
				$result['created']++;
			} elseif ($this->lastOperation === 'linked_validated') {
				$result['linked']++;
				$result['validated']++;
			} elseif ($this->lastOperation === 'linked') {
				$result['linked']++;
			} else {
				$result['skipped']++;
			}
		}

		$result['remaining'] = $this->countAccountsToReconcile($entity);
		if ($result['processed'] === $limit && $lastId > 0) {
			$result['next_id'] = $lastId;
		}

		return $result;
	}

	/**
	 * Fetch the member linked to an account with a strict entity check.
	 *
	 * @param EmergencyHousePublicAccount $account Account
	 * @return Adherent|false
	 */
	public function fetchMemberForAccount($account)
	{
		if ($account->fk_member <= 0) {
			$this->error = 'ErrorMemberLinkMissing';
			return false;
		}

		return $this->fetchMemberInEntity((int) $account->fk_member, (int) $account->entity);
	}

	/**
	 * Return non-sensitive member relationship metadata for personal export.
	 *
	 * @param EmergencyHousePublicAccount $account Account
	 * @return array{ref:string,status:int,type_id:int,type_label:string}|array{}
	 */
	public function getMemberExportMetadata($account)
	{
		$member = $this->fetchMemberForAccount($account);
		if (!$member instanceof Adherent) {
			return array();
		}

		return array(
			'ref' => (string) $member->ref,
			'status' => (int) $member->statut,
			'type_id' => (int) $member->typeid,
			'type_label' => (string) $member->type,
		);
	}

	/**
	 * Find native members sharing one normalized email in the strict entity.
	 *
	 * @param int    $entity Entity
	 * @param string $email Normalized email
	 * @return array<int, object>|false
	 */
	private function findMembersByEmail($entity, $email)
	{
		$normalizedEmail = strtolower(trim($email));
		$sql = 'SELECT rowid, statut, morphy FROM '.MAIN_DB_PREFIX.'adherent';
		$sql .= ' WHERE entity = '.((int) $entity);
		$sql .= " AND LOWER(TRIM(email)) = '".$this->db->escape($normalizedEmail)."'";
		$sql .= ' ORDER BY rowid ASC';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}

		$members = array();
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$members[] = $obj;
		}

		return $members;
	}

	/**
	 * Fetch a native member only when it belongs to the requested entity.
	 *
	 * @param int $memberId Member ID
	 * @param int $entity Entity
	 * @return Adherent|false
	 */
	private function fetchMemberInEntity($memberId, $entity)
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'adherent';
		$sql .= ' WHERE rowid = '.((int) $memberId).' AND entity = '.((int) $entity);
		$sql .= ' AND entity IN ('.$this->db->sanitize(getEntity('member')).')';
		$resql = $this->db->query($sql);
		if (!$resql || $this->db->num_rows($resql) !== 1) {
			$this->error = $resql ? 'ErrorMemberLinkInvalid' : $this->db->lasterror();
			return false;
		}

		$member = new Adherent($this->db);
		if ($member->fetch($memberId, '', 0, '', false, false) <= 0 || (int) $member->entity !== $entity) {
			$this->error = 'ErrorMemberLinkInvalid';
			return false;
		}

		return $member;
	}

	/**
	 * Validate native member field limits before calling the core object.
	 *
	 * @param array{firstname:string,lastname:string,email:string,phone:string} $profile Profile
	 * @return bool
	 */
	private function profileFitsNativeMember($profile)
	{
		if (
			trim($profile['firstname']) === ''
			|| trim($profile['lastname']) === ''
			|| dol_strlen($profile['firstname']) > 50
			|| dol_strlen($profile['lastname']) > 50
		) {
			$this->error = 'ErrorMemberNameTooLong';
			return false;
		}
		if (
			filter_var($profile['email'], FILTER_VALIDATE_EMAIL) === false
			|| dol_strlen($profile['email']) > 255
			|| dol_strlen($profile['phone']) > 30
		) {
			$this->error = 'ErrorMemberContactTooLong';
			return false;
		}

		return true;
	}

	/**
	 * Count active verified accounts without a native member link.
	 *
	 * @param int $entity Entity
	 * @return int
	 */
	private function countAccountsToReconcile($entity)
	{
		$sql = 'SELECT COUNT(rowid) AS total FROM '.MAIN_DB_PREFIX.'emergencyhouse_public_account';
		$sql .= ' WHERE entity = '.((int) $entity);
		$sql .= ' AND status = '.EmergencyHousePublicAccount::STATUS_ACTIVE;
		$sql .= ' AND email_verified = 1 AND fk_member IS NULL';
		$resql = $this->db->query($sql);
		$obj = $resql ? $this->db->fetch_object($resql) : false;

		return is_object($obj) ? (int) $obj->total : 0;
	}
}
