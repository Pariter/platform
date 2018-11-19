<?php

namespace Pariter\Models;

/**
 * @Source("SESSIONS")
 */
class Session extends Model {

	/**
	 * @var integer $id
	 *
	 * @Primary
	 * @Column(column="SES_ID", type="integer")
	 */
	public $id;

	/**
	 * @var string $expires
	 *
	 * @Column(column="SES_EXPIRES", type="string")
	 */
	public $expires;

	/**
	 * @var string $data
	 *
	 * @Column(column="SES_DATA", type="string")
	 */
	public $data;

}
