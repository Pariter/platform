<?php

namespace Pariter\Models;

use Dugwood\Core\Cache;
use Exception;
use Phalcon\Db\RawValue;
use Phalcon\Mvc\Model\Message;
use Phalcon\Validation;
use Phalcon\Validation\Validator\Email;

/**
 * @Source('USERS')
 * @ApiDefinition("Users")
 * @Suggest(displayName)
 */
class User extends Model {

	const cache = Cache::none;
	const key = 'user';
	const keys = 'users';

	/** User's roles, in power of 2 */
	const roleAnonymous = 1;
	const roleUser = 2;
	const roleAdmin = 4;
	const roleSelf = 8; // fake role, allow reading properties of owned objects (my personal email but not others')

	/**
	 * @var integer $id
	 *
	 * @Primary
	 * @Identity
	 * @Column(column="USR_ID", type="integer")
	 */

	public $id;

	/**
	 * @var string $email
	 *
	 * @Column(column="USR_EMAIL", type="string")
	 * @Form({user:true}, text)
	 * @Api(read=Self)
	 */
	public $email = '';

	/**
	 * @var string $created
	 *
	 * @Column(column="USR_CREATED", type="string")
	 * @Api(read=Anonymous)
	 */
	public $created = '';

	/**
	 * @var string $privateKey
	 *
	 * @Column(column="USR_PRIVATE_KEY", type="string")
	 */
	public $privateKey = '';

	/**
	 * @var string $roles
	 *
	 * @Column(column="USR_ROLES", type="integer", nullable=false)
	 * NEVER CHANGE ROLES HERE! Else users' would be able to rank up to admin
	 * @Form({admin:true}, checkboxes, binary={1,2})
	 */
	public $roles = self::roleUser;

	/**
	 * @var string $displayName
	 *
	 * @Column(column="USR_DISPLAY_NAME", type="string")
	 * @Form({user:true}, text)
	 * @Api(read=Anonymous)
	 */
	public $displayName = '';

	/**
	 * @var string $linkedinIdentifier
	 *
	 * @Unique
	 * @Column(column="USR_LINKEDIN_IDENTIFIER", type="string", nullable=true)
	 */
	public $linkedinIdentifier = null;

	/**
	 * @var string $githubIdentifier
	 *
	 * @Unique
	 * @Column(column="USR_GITHUB_IDENTIFIER", type="string", nullable=true)
	 */
	public $githubIdentifier = null;

	public function beforeSave() {

		if ($this->created <= 0) {
			$this->created = new RawValue('NOW()');
		}

		if (!$this->privateKey) {
			$this->privateKey = bin2hex(random_bytes(15));
		}

		if (($this->roles & self::roleSelf) > 0) {
			$message = new Message('Role «self» can\'t be used');
			$message->setCode(400);
			$this->appendMessage($message);
			return false;
		}

		if ($this->email) {
			$validation = new Validation();
			$validation->add('email', new Email());
			$messages = $validation->validate(['email' => $this->email]);
			if (count($messages) > 0) {
				$message = new Message('Email format is wrong');
				$message->setCode(400);
				$this->appendMessage($message);
				return false;
			}
		}

		return parent::beforeSave();
	}

	public static function upsertByProvider($provider, $identifier) {
		$column = strtolower(preg_replace('~[^a-z0-9]+~i', '', $provider)) . 'Identifier';
		$user = self::findFirst(['conditions' => '[' . $column . '] = :identifier:', 'bind' => ['identifier' => $identifier]]);
		if (!$user) {
			$user = new self();
			$user->$column = $identifier;
			if (!$user->save()) {
				throw new Exception('Can\'t create user');
			}
		}
		return $user;
	}

	public static function getAllByParameters(array $parameters = []) {
		$offset = $parameters['offset'] ?? 0;
		$limit = min(100, $parameters['limit'] ?? 20);
		$order = $parameters['order'] ?? 'id ASC';

		return self::find(['limit' => $limit, 'offset' => $offset, 'order' => $order]);
	}

	public function getToken($id = 0, $key = '', $delay = 0) {
		if (!$id) {
			$id = $this->id;
		}
		if (!$key) {
			$key = $this->privateKey;
		}
		$oneHourRange = $_SERVER['REQUEST_TIME'] - $_SERVER['REQUEST_TIME'] % 3600 - $delay;
		return substr(sha1($oneHourRange . $this->id . $oneHourRange . $this->privateKey . $oneHourRange), 2, -2);
	}

	public static function getByToken($token) {
		$token = explode('.', $token);
		$id = (int) hexdec($token[0]);
		if (!$id || count($token) !== 2 || !($user = self::findFirst($id))) {
			return false;
		}
		/* Test also the past key, if the hour has just changed */
		if ($user->getToken($user->id, $user->privateKey) === $token[1] || $user->getToken($user->id, $user->privateKey, 3600) === $token[1]) {
			return $user;
		}
		return false;
	}

}
