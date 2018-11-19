<?php

namespace Pariter\Models;

use Dugwood\Core\Cache;
use Exception;
use Phalcon\Mvc\Model\Message;
use Phalcon\Validation;
use Phalcon\Validation\Validator\Email;

/**
 * @Source('USERS')
 */
class User extends Model {

	const cache = Cache::none;

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
	 */
	public $email = '';

	/**
	 * @var string $displayName
	 *
	 * @Column(column="USR_DISPLAY_NAME", type="string")
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

	static public function upsertByProvider($provider, $identifier) {
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

}
