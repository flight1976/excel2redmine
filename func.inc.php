<?php
require_once 'config.inc.php';


function get_user_array($redmine_web, $redmine_api_key) {
	$client = new Redmine\Client($redmine_web, $redmine_api_key);
	$users = $client->user->all([
		'limit' => 30000
	]);


	foreach ($users['users'] as $user) {
		if ($user['lastname'] != 'L') {
			$username = $user['lastname'].$user['firstname'];
			$username = trim($username);
			$users_arr[$username]['login'] = $user['login'];
		} else {
			$username = trim($user['firstname']);
			$users_arr[$username]['login'] = $user['login'];
		}//if
		$users_arr[$username]['id'] = $user['id']; 
	}//foreach
	return $users_arr;
}

function get_project_array($redmine_web, $redmine_api_key) {
        $client = new Redmine\Client($redmine_web, $redmine_api_key);
	$projects = $client->project->all([
                'limit' => 10000
        ]);

	foreach ($projects['projects'] as $project) {
		$p_identifier = $project['identifier'];
		$p_arr[$p_identifier] = $project['id'];
	}

	return $p_arr;


}

?>
