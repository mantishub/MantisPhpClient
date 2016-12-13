<?php
/**************************************************************************
 MantisBT / MantisHub PHP Client
 Copyright (c) MantisHub - Victor Boctor
 All rights reserved.
 MIT License
 **************************************************************************/

/**
 * A php client for MantisBT / MantisHub SOAP API.
 */
class MantisPhpClient {
    const CLIENT_VERSION = '1.0.0';

    /**
     * @var SoapClient The Soap Client.
     */
    private $soap_client;

    /**
     * @var string The uri for the webservice.
     */
    private $soap_uri;

    /**
     * @var string The uri for MantisBT instance.
     */
    private $mantis_uri;

    /**
     * @var string The MantisBT version.
     */
    private $mantis_version;

    /**
     * @var array The cached list of projects.
     */
    private $projects;

    /**
     * @var string The user name.
     */
    private $username;

    /**
     * @var string The user password.
     */
    private $password;

    /**
     * @var string Username returned by mc_login() this should be used for further queries to webservice.
     *              It will be different from $username in case of anonymous login where username will be
     *              blank and this one will be the actual username.
     */
    private $effective_username;

    /**
     * MantisPhpClient constructor.
     * @param string $p_soap_wsdl_url
     * @param string$p_username
     * @param string $p_password
     * @param string string $p_user_agent
     */
    public function __construct( $p_soap_wsdl_url, $p_username, $p_password, $p_user_agent = 'MantisPhpClient' ) {
        $this->soap_uri = rtrim( $p_soap_wsdl_url, ' /' );

        // Make sure url points to the webservice and not just mantisbt instance
        if ( stristr( $this->soap_uri, 'mantisconnect.php' ) === false ) {
            $this->soap_uri .= '/api/soap/mantisconnect.php';
        }

        // Make sure url starts with http:// or https://
        if ( stristr( $this->soap_uri, 'http' ) === false ) {
            $this->soap_uri = 'http://' . $this->soap_uri;
        }

        $t_suffix = stristr( $this->soap_uri, '/api' );
        $this->mantis_uri = substr( $this->soap_uri, 0, strlen( $this->soap_uri ) - strlen( $t_suffix ) );

        $this->soap_client = @new SoapClient(
            $this->soap_uri . '?wsdl',
            array(
                'location' => $this->soap_uri,
                'soap_version' => SOAP_1_1,
                'encoding' => 'UTF-8',
                'user_agent' => $p_user_agent,
                'exceptions' => 1,
                'connection_timeout' => 15,
                'style'    => SOAP_DOCUMENT,
                'use'      => SOAP_LITERAL,
                'cache_wsdl' => WSDL_CACHE_DISK,
            )
        );

        $this->mantis_version = null;
        $this->username = $p_username;
        $this->password = $p_password;
    }

    /**
     * return string The MantisBT instance URI without the suffix for the webservice end point.
     */
    public function getMantisUri() {
        return $this->mantis_uri;
    }

    /**
     * @return string The MantisBT instance URI without the suffix for the webservice end point.
     */
    public function getMantisSoapUri() {
        return $this->soap_uri;
    }

    /**
     * @throws \Exception
     */
    public function validate() {
        // check if we get functions array else the web service url is invalid or something went wrong
        if ( !is_array( $this->soap_client->getFunctions() ) ) {
            throw new Exception( "Problem connecting web service '$this->soap_uri'.", 1 );
        }
    }

    /**
     * @return  mixed
     */
    public function getResponse() {
        return $this->soap_client->__getLastResponse() . "\n" . $this->soap_client->__getLastResponse();
    }

    /**
     * @return string The MantisBT version.
     */
    public function getMantisVersion() {
        if ( $this->mantis_version === null ) {
            $this->mantis_version = $this->soap_client->mc_version();
        }

        return $this->mantis_version;
    }

    /**
     * Gets categories list of a project.
     * @param int $p_project_id <p>Project id must be supplied</p>
     * @return array <p> Result array</p>
     */
    public function getCategoriesList( $p_project_id ) {
        return $this->soap_client->mc_project_get_categories( $this->username, $this->password, $p_project_id );
    }

    /**
     * Gets the list of supported standard filters.
     * @return array of filter objects for standard filters 
     */
    public function getStandardFiltersList() {
        $t_standard_filters = array();
        $t_standard_filters[] = new Filter( 'all', lang_get_text( 'all_issues' ) );

        $t_anonymous_access = $this->isAnonymousAccess();

        $t_mantis_version = $this->getMantisVersion();
        if ( version_compare( $t_mantis_version, '1.2.16dev' ) >= 0 ) {
            if ( !$t_anonymous_access ) {
                $t_standard_filters[] = new Filter( 'assigned_to_me', lang_get_text( 'assigned_to_me' ) );
            }

            $t_standard_filters[] = new Filter( 'unassigned', lang_get_text( 'unassigned' ) );

            if ( !$t_anonymous_access ) {
                $t_standard_filters[] = new Filter( 'reported_by_me', lang_get_text( 'reported_by_me' ) );
                $t_standard_filters[] = new Filter( 'monitored_by_me', lang_get_text( 'monitored_by_me' ) );
            }
        }

        return $t_standard_filters;
    }

    /**
     * Gets Filters list of a project.
     * @param int $p_project_id <p>Project id must be supplied</p>
     * @return array <p> Result array</p>
     */
    public function getCustomFiltersList( $p_project_id ) {
        $t_result = $this->soap_client->mc_filter_get( $this->username, $this->password, $p_project_id );
        if ( !is_array( $t_result ) ) {
            return array();
        }

        return $t_result;
    }

    /**
     * Gets versions list of a project.
     * @param int $p_project_id <p>Project id must be supplied</p>
     * @return array <p> Result array</p>
     */
    public function getVersionsList( $p_project_id ) {
        return $this->soap_client->mc_project_get_versions( $this->username, $this->password, $p_project_id );
    }

    /**
     * Gets MantisBT configuration of given variable
     * @param string $p_config_var <p>MantisBT variable for configuration</p>
     * @return array <p> Result array</p>
     */
    public function getConfigString( $p_config_var ) {
        try
        {
            return $this->soap_client->mc_config_get_string( $this->username, $this->password, $p_config_var );
        }
        catch (Exception $e)
        {
            error_log( $e );
            return null;
        }
    }

    /**
     * Gets Enum Status
     * @return array <p> Result array</p>
     */
    public function getEnumStatus() {
        return $this->soap_client->mc_enum_status( $this->username, $this->password );
    }

    /**
     * Gets Enum Resolution
     * @return array <p> Result array</p>
     */
    public function getEnumResolution() {
        return $this->soap_client->mc_enum_resolutions( $this->username, $this->password );
    }

    /**
     * Gets Enum Access Levels
     * @return array <p> Result array</p>
     */
    public function getEnumAccessLevel() {
        return $this->soap_client->mc_enum_access_levels( $this->username, $this->password );
    }

    /**
     * Gets Enum Priority
     * @return array <p> Result array</p>
     */
    public function getEnumPriority() {
        return $this->soap_client->mc_enum_priorities( $this->username, $this->password );
    }

    /**
     * Gets Enum Severity
     * @return array <p> Result array</p>
     */
    public function getEnumSeverity() {
        return $this->soap_client->mc_enum_severities( $this->username, $this->password );
    }

    /**
     * Gets Enum Eta
     * @return array <p> Result array</p>
     */
    public function getEnumEta() {
        return $this->soap_client->mc_enum_etas( $this->username, $this->password );
    }

    /**
     * Gets Enum Project Status
     * @return array <p> Result array</p>
     */
    public function getEnumProjectStatus() {
        return $this->soap_client->mc_enum_project_status( $this->username, $this->password );
    }

    /**
     * Gets Enum Project View States
     * @return array <p> Result array</p>
     */
    public function getEnumProjectViewState() {
        return $this->soap_client->mc_enum_project_view_states( $this->username, $this->password );
    }

    /**
     * Gets Enum Projection
     * @return array <p> Result array</p>
     */
    public function getEnumProjection() {
        return $this->soap_client->mc_enum_projections( $this->username, $this->password );
    }

    /**
     * Gets Enum Reproducibility
     * @return array <p> Result array</p>
     */
    public function getEnumReproducibility() {
        return $this->soap_client->mc_enum_reproducibilities( $this->username, $this->password );
    }

    /**
     * Gets Enum View State
     * @return array <p> Result array</p>
     */
    public function getEnumViewState() {
        return $this->soap_client->mc_enum_view_states( $this->username, $this->password );
    }

    /**
     * Gets Project Users
     * @param int $p_project_id <p>Project id must be supplied</p>
     * @param int $p_access_id <p>Access id</p>
     * @return array <p> Result array</p>
     */
    public function getProjectUsers( $p_project_id, $p_access_id ) {
        return $this->soap_client->mc_project_get_users( $this->username, $this->password, $p_project_id, $p_access_id );
    }

    /**
     * @return array The list of accessible projects.
     */
    public function getProjects() {
        if ( $this->projects === null ) {
            $this->projects = $this->soap_client->mc_projects_get_user_accessible( $this->username, $this->password );
        }

        return $this->projects;
    }

    /**
     * @return StdClass|null Gets the information about the specified project or null if not found.
     */
    public function getProjectById( $p_project_id ) {
        $t_projects = $this->getProjects();
        return $this->getProjectFromProjectTree( $p_project_id, $t_projects );
    }

  /**
   * @param string $p_project_name
   * @return int
   */
    public function getProjectIdByName( $p_project_name ) {
        return $this->soap_client->mc_project_get_id_from_name( $this->username, $this->password, $p_project_name );
    }

    /**
     * @return StdClass|null A recursive method to find a project by id in a project and all its sub-projects.
     */
    private function getProjectFromProjectTree( $p_project_id, $p_projects ) {
        foreach ( $p_projects as $t_project ) {
            if ( $t_project->id == $p_project_id ) {
                return $t_project;
            }

            if ( isset( $t_project->subprojects ) && is_array( $t_project->subprojects ) ) {
                $t_project_found = $this->getProjectFromProjectTree( $p_project_id, $t_project->subprojects );
                if ( $t_project_found !== null ) {
                    return $t_project_found;
                }
            }
        }

        return null;
    }

    /**
     * Checks if a user exists.
     *
     * @param StdClass $p_user array that can contain id, name, or email.
     * @return true found, false otherwise.
     * @throws Exception
     */
    public function userExists( $p_user ) {
        $t_mantis_version = $this->getMantisVersion();

        if ( version_compare( $t_mantis_version, '1.2.16dev' ) >= 0 ) {
            try {
                $this->soap_client->mc_project_get_issues_for_user( $this->username, $this->password, 'ALL_PROJECTS', 'reported', $p_user, 1, 1 );
                return true;
            } catch (Exception $e) {
                return false;
            }
        } else {
            throw new Exception( 'userExists() can only be called on v1.2.16+.' );
        }
    }

    /**
     * Gets issues list by given project and/or custom filter. Valid Username and Password must be provided.
     * @param int $p_project_id <p>Project id must be supplied</p>
     * @param int $p_current_page [optional] <p>Current Page Number</p>
     * @param int $p_filter_id [optional] <p>Custom Filter Id</p>
     * @return array <p> Result array</p>
     */
    public function getIssues( $p_project_id, $p_current_page = 1, $p_filter_id = 0 ) {
        global $g_issues_list_limit;
        $t_processed = false;

        if ( is_numeric( $p_filter_id ) && ( (int) $p_filter_id > 0 ) ) {
            $t_custom_filter_id = (int) $p_filter_id;

            $t_result = $this->soap_client->mc_filter_get_issues( $this->username, $this->password, $p_project_id, $t_custom_filter_id, $p_current_page, $g_issues_list_limit );
            $t_processed = true;
        } else {
            $t_mantis_version = $this->getMantisVersion();

            if ( version_compare( $t_mantis_version, '1.2.16dev' ) >= 0 ) {
                $t_target_user = array();
                $t_target_user['name'] = $this->effective_username;

                switch ( $p_filter_id ) {
                    case 'assigned_to_me':
                        $t_result = $this->soap_client->mc_project_get_issues_for_user( $this->username, $this->password, $p_project_id, 'assigned', $t_target_user, $p_current_page, $g_issues_list_limit );
                        $t_processed = true;
                        break;
                    case 'unassigned':
                        $t_target_user = array();
                        $t_target_user['id'] = 0;
                        $t_result = $this->soap_client->mc_project_get_issues_for_user( $this->username, $this->password, $p_project_id, 'assigned', $t_target_user, $p_current_page, $g_issues_list_limit );
                        $t_processed = true;
                        break;
                    case 'monitored_by_me':
                        $t_result = $this->soap_client->mc_project_get_issues_for_user( $this->username, $this->password, $p_project_id, 'monitored', $t_target_user, $p_current_page, $g_issues_list_limit );
                        $t_processed = true;
                        break;
                    case 'reported_by_me':
                        $t_result = $this->soap_client->mc_project_get_issues_for_user( $this->username, $this->password, $p_project_id, 'reported', $t_target_user, $p_current_page, $g_issues_list_limit );
                        $t_processed = true;
                        break;
                }
            }
        }

        // if no matching filter handler, return all issues
        if ( !$t_processed ) {
            $t_result = $this->soap_client->mc_project_get_issues( $this->username, $this->password, (int)$p_project_id, (int)$p_current_page, (int)$g_issues_list_limit );
        }

        return $t_result;
    }

    /**
     * Creates an issue.
     * @param array $p_data <p>New Issue detail</p>
     * @return int <p> New Issue ID </p>
     */
    public function addIssue( $p_data ) {
        return $this->soap_client->mc_issue_add( $this->username, $this->password, $p_data );
    }

    /**
     * Gets issue detail by providing issue id
     * @param int $p_issue_id <p>Issue id got get detail for</p>
     * @return array <p> Result array</p>
     */
    public function getIssue( $p_issue_id ) {
        $t_issue = $this->soap_client->mc_issue_get( $this->username, $this->password, $p_issue_id );

        if ( !isset( $t_issue->custom_fields ) ) {
            $t_issue->custom_fields = array();
        }

        return $t_issue;
    }

    /**
     * Updates Issue detail
     * @param int $p_issue_id <p>Issue id</p>
     * @param array $p_data <p>Issue Data Array</p>
     * @return boolean
     */
    public function updateIssue( $p_issue_id, $p_data) {
        return $this->soap_client->mc_issue_update( $this->username, $this->password, $p_issue_id, $p_data );
    }

    /**
     * Delete the issue with the specified id.
     * @param int $p_issue_id <p>Issue id got get detail for</p>
     */
    public function deleteIssue( $p_issue_id ) {
        return $this->soap_client->mc_issue_delete( $this->username, $this->password, $p_issue_id );
    }

    /**
     * Gets the custom fields definitions for the specified project.
     * @param int $p_project_id <p>The id of the project to get the fields for.</p>
     * @return array <p>Result array</p>
     */
    public function getCustomFieldDefinitions( $p_project_id ) {
        return $this->soap_client->mc_project_get_custom_fields( $this->username, $this->password, $p_project_id );
    }

    /**
     * Adds New Note
     * @param int $p_issue_id <p>Issue id</p>
     * @param string $p_text <p>The note text.</p>
     * @return int <p> New Note ID </p>
     */
    public function addNote( $p_issue_id, $p_text ) {
        $t_data = array(
            'text' => $p_text,
        );

        return $this->soap_client->mc_issue_note_add( $this->username, $this->password, $p_issue_id, $t_data );
    }

  /**
   * @param int $p_issue_id
   * @param string $p_file_name
   * @param string $p_file_type
   * @param string $p_file_path
   * @return mixed
   */
    public function addAttachment( $p_issue_id, $p_file_name, $p_file_type, $p_file_path ) {
        $t_content  = file_get_contents( $p_file_path );
        return $this->soap_client->mc_issue_attachment_add( $this->username, $this->password, $p_issue_id, $p_file_name, $p_file_type, $t_content );
    }

    /**
     * Gets note detail by providing issue_id and note_id
     * @param int $p_issue_id <p>Issue id got get issue detail</p>
     * @param int $p_note_id <p>Note id to get required note object</p>
     * @return array <p> Result array</p>
     */
    public function getNote( $p_issue_id, $p_note_id ) {
        $t_issue_detail = $this->getIssue( $p_issue_id );

        if ( isset( $t_issue_detail ) ) {
            foreach ( $t_issue_detail->notes as $t_note ) {
                if ( isset( $t_note ) && ( $t_note->id == $p_note_id ) ) {
                    return $t_note; // required note found, return note array object
                }
            }
        }

        return null;
    }

    /**
     * Update Note
     *
     * @param int $p_id <p>Note id</p>
     * @param string $p_text <p>The note text.</p>
     * @throws Exception
     */
    public function updateNote( $p_id, $p_text ) {
        $t_note_data = array(
            'id'   => $p_id,
            'text' => $p_text,
        );

        $t_result = $this->soap_client->mc_issue_note_update( $this->username, $this->password, $t_note_data );
        if ( !$t_result ) {
            throw new Exception( 'Unable to update issue note.' );
        }
    }

    /**
     * Delete Note
     * @param int $p_note_id <p>Note id got get detail for</p>
     * @return boolean
     */
    public function deleteNote( $p_note_id ) {
        return $this->soap_client->mc_issue_note_delete( $this->username, $this->password, $p_note_id );
    }

    /**
     * Check if anonymous access is allowed.
     * @return null or the user data array (id, name, real_name, email, access_level, and timezone).
     */
    public function checkAnonymousAccess() {
        try {
            $t_result = $this->soap_client->mc_login( '', '' );
        } catch ( Exception $ex ) {
            return false;
        }

        return true;
    }

    /**
     * Authenticates user
     * @return array The user data array (id, name, real_name, email, access_level, and timezone).
     * @throws Exception
     */
    public function authenticateUser() {
        $t_user_data = array();

        $t_mantis_version = $this->getMantisVersion();

        try {
            $t_result = $this->soap_client->mc_login( $this->username, $this->password );

            $t_user_data['name'] = $t_result->account_data->name;
            $t_user_data['id'] = $t_result->account_data->id;
            $t_user_data['real_name'] = isset( $t_result->account_data->real_name ) ? $t_result->account_data->real_name : '';
            $t_user_data['email'] = $t_result->account_data->email;
            $t_user_data['access_level'] = $t_result->access_level;
            $t_user_data['timezone'] = $t_result->timezone;

            $this->effective_username = $t_result->account_data->name;
        } catch ( Exception $ex ) {
            // log user logins for mining later.
            error_log(
                'mantisphpclient-login-failure, ' .
                $this->mantis_uri . ', ' .
                $t_mantis_version . ', ' .
                $this->username );

            throw $ex;
        }

        return $t_user_data;
    }

  /**
   * @param string $p_effective_username
   */
    public function setEffectiveUserName( $p_effective_username ) {
        $this->effective_username = $p_effective_username;
    }

  /**
   * @return bool
   */
    public function isAnonymousAccess() {
        return !empty( $this->effective_username ) && $this->username != $this->effective_username;
    }

    /**
     * @return StdClass the default project.
     */
    public function getDefaultProject() {
        return $this->getUserPreference( 'default_project' );
    }

    /**
     * @return string the user language.
     */
    public function getUserLanguage() {
        return $this->getUserPreference( 'language' );
    }

    /**
     * Gets the specified issue attachment.
     * @param int $p_issue_id The issue id.
     * @param int $p_file_id The attachment id.
     * @return StdClass attachment content.
     */
    public function getIssueAttachment( $p_issue_id, $p_file_id ) {
        $t_issue = $this->getIssue( $p_issue_id );

        $t_attachment_info = null;

        foreach ( $t_issue->attachments as $t_attachment ) {
            if ( (int)$t_attachment->id != (int)$p_file_id ) {
                continue;
            }

            $t_attachment_info = $t_attachment;
            break;
        }

        if ( $t_attachment_info == null ) {
            return null;
        }

        $t_attachment_info->content = $this->soap_client->mc_issue_attachment_get( $this->username, $this->password, $p_file_id );

        return $t_attachment_info;
    }

    /**
     * Set the timezone based on the user's time zone, or the default timezone for the remote instance if the user doesn't
     * have their timezone set.
     * @return void
     */
    public function setTimeZone() {
        // TODO: Should the timezone be cached?
        $t_timezone = $this->getUserPreference( 'timezone' );

        if ( empty( $t_timezone ) ) {
            $t_timezone = $this->getConfigString( 'default_timezone' );
        }

      /**
       *  Attempt to set the current timezone to the user's desired value
       *  Note that PHP 5.1 on RHEL/CentOS doesn't support the timezone functions
       *  used here so we just skip this action on RHEL/CentOS platforms.
       */
        if ( function_exists( 'timezone_identifiers_list' ) ) {
            if ( !@date_default_timezone_set( $t_timezone ) ) {
                @date_default_timezone_set( 'America/Los_Angeles' );
            }
        }
    }

    /**
     * @return array The specified user preference.
     * MantisBT returns a incorrect value for 0 (ALL_PROJECTS), from all other projects.  The correct value seems to be
     * returned even if the supplied value has no corresponding project.  In other words, a project is != 0 will work.
     */
    private function getUserPreference( $p_preference ) {
        return $this->soap_client->mc_user_pref_get_pref( $this->username, $this->password, /* project_id */ 1, $p_preference );
    }
}

/**
 * Filter class used to capture information about a single filter.
 */
class Filter {
  /**
   * @var int
   */
    public $id;

  /**
   * @var string
   */
    public $name;

  /**
   * Filter constructor.
   * @param int $id
   * @param string $name
   */
    public function __construct( $id, $name ) {
        $this->id = $id;
        $this->name = $name;
    }
}

if(!function_exists('lang_get_text')){
  /**
   * @param string $str
   * @return string
   */
  function lang_get_text($str){
    return $str;
  }
}