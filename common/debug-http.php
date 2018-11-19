<?php

use Dugwood\Core\Checker\Html;

if (strpos($return, '<!DOCTYPE') === 0) {
	$return = str_replace('</body>', Html::getCode($return) . '</body>', $return);
}

/* Check cache header */
$headersList = implode("\n", headers_list());
if (preg_match('~^Cache-Control: .*?max-age=(\d+).*?s-maxage=(\d+).*$~m', $headersList, $cacheHeader) !== 1) {
	trigger_error('No cache header');
} else {
	if ($application->request->isGet() && $cacheHeader[2] > 0 && preg_match('~^Last-Modified:~m', $headersList) !== 1) {
		trigger_error('Missing Last-Modified for GET request with surrogate cache (' . $application->request->getServer('REQUEST_URI') . ')');
	}
	$sessionWasHit = in_array('Pariter\\Plugins\\Session', get_declared_classes());
	if ($application->request->isPost() && $cacheHeader[1] + $cacheHeader[2] > 0) {
		trigger_error('Cache on POST request (' . $application->request->getServer('REQUEST_URI') . ')');
	} elseif ($application->request->isGet() && $cacheHeader[1] + $cacheHeader[2] <= 0 && strpos($application->request->getServer('REQUEST_URI'), '/vvv-validator/') === false && strpos($headersList, 'Status: ') === false && strpos($headersList, 'X-Ignore-Cache: 1') === false && $di['session']->isStarted() === false) {
		trigger_error('No cache on GET request (' . $application->request->getServer('REQUEST_URI') . ' - ' . $cacheHeader[0] . ')');
	} elseif ($sessionWasHit === true && $cacheHeader[2] > 0) {
		trigger_error('Surrogate cache on request (' . $application->request->getServer('REQUEST_URI') . ' - ' . $cacheHeader[0] . ')');
	} elseif ($di['session']->isStarted() && !isset($di['config']->needAcl)) {
		trigger_error('Session started without ACL (' . $application->request->getServer('REQUEST_URI') . ' - ' . $cacheHeader[0] . ')');
	}
}
if (error_get_last()) {
	$return = 'At least one error occurred: ' . var_export(error_get_last(), true) . '<br/>' . $return;
}
if (strpos($headersList, 'application/json') !== false && (strpos($return, 'xdebug-') !== false || error_get_last())) {
	header('Content-Type: text/html', true);
}
