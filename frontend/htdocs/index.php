<?php

use Phalcon\Mvc\Application;
use Pariter\Common\Kernel;

try {
	/* Sending variable «_url» directly */
	if (isset($_SERVER['QUERY_STRING']) && strpos($_SERVER['QUERY_STRING'], '&_url=') !== false) {
		throw new Exception('Forged call');
	}
	include __DIR__ . '/../../common/Kernel.php';
	$kernel = new Kernel();
	$di = $kernel->getDI('frontend');

	/**
	 * Handle the request
	 */
	$application = new Application($di);

	$return = $application->handle()->getContent();

	/* HTML validator */
	if ($di['config']->environment === 'dev' && $di['config']->debug === true) {
		include __DIR__ . '/../../common/debug-http.php';
	}

	$di['cache']->sendBanHeader();
	echo $return;
} catch (Exception $exception) {
	ob_end_clean();
	header('Status: 503');
	echo 'An error has occurred (front).';
	if (isset($di['config']) && $di['config']->debug === true && $di['config']->environment === 'dev' || isset($_SERVER['SERVER_ID']) && $_SERVER['SERVER_ID'] === '99') {
		echo '<pre>Exception ' . $exception->getFile() . '@' . $exception->getLine() . ' : ' . $exception->getMessage() . "\n\nStacktrace:\n" . $exception->getTraceAsString() . '</pre>';
	}
	trigger_error('Exception ' . $exception->getFile() . '@' . $exception->getLine() . ' : ' . $exception->getMessage(), E_USER_WARNING);
	if (strpos($exception->getMessage(), 'Volt directory') !== false) {
		$di['cache']->updateRelease(true);
	}
}
