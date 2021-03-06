<?php
/**
 * Login based on a SAML/CAS source
 */

if (elgg_is_logged_in()) {
	register_error(elgg_echo('simplesaml:error:loggedin'));
	forward(REFERER);
}

$source = get_input('saml_source');
if (empty($source)) {
	register_error(elgg_echo('simplesaml:error:no_source'));
	forward(REFERER);
}
/**
 * Login based on a SAML/CAS source
 */

if (elgg_is_logged_in()) {
	register_error(elgg_echo('simplesaml:error:loggedin'));
	forward(REFERER);
}

$source = get_input('saml_source');
if (empty($source)) {
	register_error(elgg_echo('simplesaml:error:no_source'));
	forward(REFERER);
}

$label = simplesaml_get_source_label($source);
if (!simplesaml_is_enabled_source($source)) {
	register_error(elgg_echo('simplesaml:error:source_not_enabled', [$label]));
	forward(REFERER);
}

try {
	$saml_auth = new SimpleSAML_Auth_Simple($source);
} catch (Exception $e) {
	register_error(elgg_echo('simplesaml:error:class', [$e->getMessage()]));
	forward(REFERER);
}

// make sure we can forward you to the correct url
$last_forward = simplesaml_get_from_session('last_forward_from');
if (!isset($last_forward)) {
	simplesaml_store_in_session('last_forward_from', $_SERVER['REFERER']);
}

$forward_url = REFERER;

// login with SAML
if (!$saml_auth->isAuthenticated()) {
	// not logged in on IDP, so do that
	$saml_auth->login();
} else {
	// user is authenticated with IDP, so check in Elgg
	$saml_attributes = simplesaml_get_authentication_attributes($saml_auth, $source);
	
	// check for additional authentication rules
	if (!simplesaml_validate_authentication_attributes($source, $saml_attributes)) {
		// not authorized
		register_error(elgg_echo('simplesaml:error:attribute_validation', [$label]));
		
		// make sure we don't force login
		simplesaml_store_in_session('simplesaml_disable_sso', true);
		
		forward();
	}
	
	// save the attributes for further use
	simplesaml_store_in_session('saml_attributes', $saml_attributes);
	simplesaml_store_in_session('saml_source', $source);
	
	// make sure we can find all users (even unvalidated)
	$hidden = access_get_show_hidden_status();
	access_show_hidden_entities(true);
	
	$user = simplesaml_find_user($source, $saml_attributes);
	if (!empty($user)) {
		// found a user, so login
		try {
			// check for the persistent login plugin setting
			$persistent = false;
			if (elgg_get_plugin_setting("{$source}_remember_me", 'simplesaml')) {
				$persistent = true;
			}
			
			// login the user
			login($user, $persistent);
			
			// forward to correct place
			$forward_url = simplesaml_get_from_session('last_forward_from', '');
			simplesaml_remove_from_session('last_forward_from');
			
			system_message(elgg_echo('loginok'));
		} catch (Exception $e) {
			// report the error
			register_error($e->getMessage());
			
			// make sure we don't force login
			simplesaml_store_in_session('simplesaml_disable_sso', true);
			
			// forward to front page
			$forward_url = '';
		}
		
		// unset session vars
		simplesaml_remove_from_session('saml_attributes');
		simplesaml_remove_from_session('saml_source');
		
	} else {
		// check if we can automaticly create an account for this user
		if (simplesaml_check_auto_create_account($source, $saml_attributes)) {
			// we have enough information to create the account so let's do that
			$forward_url = "action/simplesaml/register?saml_source={$source}";
			$forward_url = elgg_add_action_tokens_to_url($forward_url);
		} else {
			// no user found, so forward to a different page
			$forward_url = "saml/no_linked_account/{$source}";
			
			system_message(elgg_echo('simplesaml:login:no_linked_account', [$label]));
		}
	}
	
	// restore hidden settings
	access_show_hidden_entities($hidden);
}

forward($forward_url);
