<?php
include dirname( dirname( __FILE__ ) ) . '/MantisPhpClient.php';

# TODO:
# Set the username
# Set the api token or password
# Set the project name
# Set the url
#
# For information about API tokens, check the following blog post:
# http://blog.mantishub.com/2015/12/21/using-api-tokens-to-access-mantishub/

$mantis_user = 'username';
$mantis_pass = 'api-token-or-password';  # API token (v1.3) or password (v1.2)
$mantis_project = 'project-name';
$mantis_url = 'https://mantis.example.com';

$mantis = new MantisPhpClient( $mantis_url, $mantis_user, $mantis_pass, 'SampleCreateIssue/v1.0' );

$issue = array(
	'project' => array( 'name' => $mantis_project ),
	'category' => 'General',
	'summary' => 'Sample Summary ' . time(),
	'description' => 'Sample Description ' . time(),
);

$return = $mantis->addIssue( $issue );

