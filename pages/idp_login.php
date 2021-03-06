<?php
/**
 * Show the login form to external users, so they can login to the external site using this sites credentials
 *
 * No credentials will be provided to the external site, only a name, email and a generated UID
 */

// where to go after authentication
$returnTo = get_input('ReturnTo');
if (!empty($returnTo)) {
	if (elgg_is_logged_in()) {
		forward($returnTo);
	} else {
		simplesaml_store_in_session('last_forward_from', $returnTo);
	}
}

// unset some extends
simplesaml_unextend_login_form();

// disable registration for this page
elgg_set_config('allow_registration', false);

// get page elements
$title_text = elgg_echo('login');

$body = elgg_view_form('login');

// make the page
$page_data = elgg_view_layout('one_column', [
	'title' => $title_text,
	'content' => $body,
]);

// draw the page
echo elgg_view_page($title_text, $page_data);
