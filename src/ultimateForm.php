<?php

/**
 * A class for the easy creation of webforms.
 * 
 * SUPPORTS:
 * - Form stickyness
 * - All HTML fieldtypes
 * - Preset field types: valid e-mail input field, textarea which must contain at least one line containing two values
 * - Setup error correction: duplicated fields will result in the form not displaying
 * - Output to CSV, e-mail, confirmation, screen and further processing as an array
 * - Display of the form as a series of paragraphs, CSS (using divs and spans) or a table; styles can be set in a stylesheet
 * - Presentation option of whether colons appear between the title and the input field
 * - The ability to add descriptive notes to a field in addition to the title
 * - Specification of required fields
 * - By default, the option to count white-space only as an empty submission (useful when specifying as a required field)
 * - The option to trim white space surrounding submitted fields
 * - Valid XHTML1.0 Transitional code
 * - Accessibility code for form elements
 * - Customisable submit button text accesskey and location (start/end of form)
 * - Regular expression hooks for various widget types
 * - Templating mechanism
 * - The ability to set elements as non-editable
 * - Ability to generate form widgets automatically by reading a database structure (dataBinding facility)
 * - Group validation rules to ensure that at least one field is completed, that all are the same or all are different
 * - GET support available
 * - Uploaded files can be attached to e-mails
 * - Uploaded zip files can be automatically unzipped
 * - UTF-8 character encoding
 * 
 * REQUIREMENTS:
 * - PHP5 or above (PHP4.3 will run with slight modification)
 * - Runs in register_globals OFF mode for security
 * - Requires libraries application.php and pureContent.php
 * 
 * APACHE ENVIRONMENT SETUP
 * 
 * The following are required for the script to work correctly; if not, an error will be shown
 * If attempting to set in .htaccess, remove admin_ from the directives
 * 
 * <code>
 * php_flag register_globals 0
 * php_flag display_errors 0
 * php_flag magic_quotes_gpc 0
 * php_flag magic_quotes_sybase 0
 * php_value error_reporting 2047
 * 
 * # If using file uploads also include the following and set a suitable amount in MB; upload_max_filesize must not be more than post_max_size
 * php_admin_flag file_uploads 1
 * php_admin_value upload_max_filesize 10M // Only way of setting the maximum size
 * php_admin_value post_max_size 10M
 * </code>
 * 
 * @package ultimateForm
 * @license	http://opensource.org/licenses/gpl-license.php GNU Public License
 * @author	{@link http://www.geog.cam.ac.uk/contacts/webmaster.html Martin Lucas-Smith}, University of Cambridge
 * @copyright Copyright  2003-10, Martin Lucas-Smith, University of Cambridge
 * @version 1.15.1
 */
class form
{
	## Prepare variables ##
	
	# Principal arrays
	var $elements = array ();					// Master array of form element setup
	var $form;									// Master array of posted form data
	var $outputData;							// Master array of arranged data for output
	var $outputMethods = array ();				// Master array of output methods
	
	# Main variables
	var $name;									// The name of the form
	var $location;								// The location where the form is submitted to
	var $duplicatedElementNames = array ();		// The array to hold any duplicated form field names
	var $formSetupErrors = array ();			// Array of form setup errors, to which any problems can be added
	var $elementProblems = array ();			// Array of submitted element problems
	var $externalProblems = array ();			// Array of external element problems as inserted by the calling applications
	var $validationRules = array ();			// Array of validation rules
	var $databaseConnection = NULL;				// Database connection
	var $html = NULL;							// Compiled HTML, obtained by using $html = $form->getHtml () after $form->process ();
	var $prefixedGroups = array ();				// Groups of element names when using prefixing in dataBinding
	var $attachments = array ();				// Array of attachments
	
	# State control
	var $formPosted;							// Flag for whether the form has been posted
	var $formDisplayed = false;					// Flag for whether the form has been displayed
	var $setupOk = false;						// Flag for whether the form has been set up OK
	var $headingTextCounter = 1;				// Counter to enable uniquely-named fields for non-form elements (i.e. headings), starting at 1 #!# Get rid of this somehow
	var $uploadProperties;						// Data store to cache upload properties if the form contains upload fields
	var $hiddenElementPresent = false;			// Flag for whether the form includes one or more hidden elements
	var $dataBinding = false;					// Whether dataBinding is in use; if so, this will become an array containing connection variables
	
	# Output configuration
	var $configureResultEmailRecipient;							// The recipient of an e-mail
	var $configureResultEmailRecipientSuffix;					// The suffix used when a select field is used as the e-mail receipient but the selectable items are only the prefix to the address
	var $configureResultEmailAdministrator;						// The from field of an e-mail
	var $configureResultFileFilename;							// The file name where results are written
	var $configureResultConfirmationEmailRecipient = '';		// The recipient of any confirmation e-mail
	var $configureResultConfirmationEmailAbuseNotice = true;	// Whether to include an abuse report notice in any confirmation e-mail sent
	var $configureResultEmailedSubjectTitle = array ();			// An array to hold the e-mail subject title for either e-mail result type
	var $configureResultScreenShowUnsubmitted;					// Whether, in screen results mode, unsubmitted widgets that are not required will be listed
	var $configureResultEmailShowUnsubmitted;					// Whether, in e-mail results mode, unsubmitted widgets that are not required will be listed
	var $configureResultConfirmationEmailShowUnsubmitted;		// Whether, in e-mail confirmation results mode, unsubmitted widgets that are not required will be listed
	
	# Supported output types
	var $supportedTypes = array ('file', 'email', 'confirmationEmail', 'screen', 'processing', 'database');
	var $displayTypes = array ('tables', 'css', 'paragraphs', 'templatefile');
	
	# Constants
	var $timestamp;
	var $minimumPhpVersion = 5;	// md5_file requires 4.2+; file_get_contents and is 4.3+; function process (&$html = NULL) requires 5.0
	var $escapeCharacter = "'";		// Character used for escaping of output	#!# Currently ignored in derived code
	
	# Specify available arguments as defaults or as NULL (to represent a required argument)
	var $argumentDefaults = array (
		'get'								=> false,							# Enable GET support instead of (default) POST
		'name'								=> 'form',							# Name of the form
		'div'								=> 'ultimateform',					# The value of <div class=""> which surrounds the entire output (or false for none)
		'displayPresentationMatrix'			=> false,							# Whether to show the presentation defaults
		'displayTitles'						=> true,							# Whether to show user-supplied titles for each widget
		'titleReplacements'					=> array (),						# Global replacement of values in titles (mainly of use when dataBinding)
		'displayDescriptions'				=> true,							# Whether to show user-supplied descriptions for each widget
		'displayRestrictions'				=> true,							# Whether to show/hide restriction guidelines
		'display'							=> 'tables',						# Whether to display the form using 'tables', 'css' (CSS layout) 'paragraphs' or 'template'
		'displayTemplate'					=> '',								# Either a filename or a (long) string containing placemarkers
		'displayTemplatePatternWidget'		=> '{%element}',					# The pattern used for signifying element name widget positions when templating
		'displayTemplatePatternLabel'		=> '{[%element]}',					# The pattern used for signifying element name label positions (optional) when templating
		'displayTemplatePatternSpecial'		=> '{[[%element]]}',				# The pattern used for signifying element name special item positions (e.g. submit, reset, problems) when templating
		'classShowType'						=> true,							# Whether to include the widget type within the class list for the container of the widget (e.g. tr in 'tables' mode)
		'debug'								=> false,							# Whether to switch on debugging
		'displayColons'						=> true,							# Whether to show colons after the initial description
		'whiteSpaceTrimSurrounding'			=> true,							# Whether to trim surrounding white space in any forms which are submitted
		'whiteSpaceCheatAllowed'			=> false,							# Whether to allow people to cheat submitting whitespace only in required fields
		'reappear'							=> false,							# Whether to keep the form visible after successful submission (useful for search forms, etc., that should reappear)
		'formCompleteText'					=> 'Many thanks for your input.',	# The form completion text (or false if not to display it at all)
		'submitButtonPosition'				=> 'end',							# Whether the submit button appears at the end or the start/end/both of the form
		'submitButtonText'					=> 'Submit!',						# The form submit button text
		'submitButtonAccesskey'				=> 's',								# The form submit button accesskey
		'submitButtonTabindex'				=> false,							# The form submit button tabindex (if any)
		'submitButtonImage'					=> false,							# Location of an image to replace the form submit button
		'refreshButton'						=> false,							# Whether to include a refresh button (i.e. submit form to redisplay but not process)
		'refreshButtonAtEnd'				=> true,							# Whether the refresh button appears at the end or the start of the form
		'refreshButtonText'					=> 'Refresh!',						# The form refresh button text
		'refreshButtonAccesskey'			=> 'r',								# The form refresh button accesskey
		'refreshButtonTabindex'				=> false,							# The form refresh button tabindex (if any)
		'refreshButtonImage'				=> false,							# Location of an image to replace the form refresh button
		'resetButton'						=> false,							# Whether the reset button is visible (note that this must be switched on in the template mode to appear, even if the reset placemarker is given)
		'resetButtonText'					=> 'Clear changes',					# The form reset button
		'resetButtonAccesskey'				=> 'r',								# The form reset button accesskey
		'resetButtonTabindex'				=> false,							# The form reset button tabindex (if any)
		'warningMessage'					=> false,							# The form incompletion message (a specialised default is used)
		'requiredFieldIndicator'			=> true,							# Whether the required field indicator is to be displayed (top / bottom/true / false) (note that this must be switched on in the template mode to appear, even if the reset placemarker is given)
		'requiredFieldClass'				=> 'required',						# The CSS class used to mark a widget as required
		'submitTo'							=> false,							# The form processing location if being overriden
		'nullText'							=> 'Please select',					# The 'null' text for e.g. selection boxes
		'linebreaks' 						=> true,							# Widget-based linebreaks (top level default)
		'opening'							=> false,							# Optional starting datetime as an SQL string
		'closing'							=> false,							# Optional closing datetime as an SQL string
		'validUsers'						=> false,							# Optional valid user(s) - if this is set, a user will be required. To set, specify string/array of valid user(s), or '*' to require any user
		'user'								=> false,							# Explicitly-supplied username (if none specified, will check for REMOTE_USER being set
		'userKey'							=> false,							# Whether to log the username, as the key
		'loggedUserUnique'					=> false,							# Run in user-uniqueness mode, making the key of any CSV the username and checking for resubmissions
		'timestamping'						=> false,							# Add a timestamp to any CSV entry
		'ipLogging'							=> false,							# Add the user IP address to any CSV entry
		'escapeOutput'						=> false,							# Whether to escape output in the processing output ONLY (will not affect other types)
		'emailIntroductoryText'				=> '',								# Introductory text for e-mail output type
		'emailShowFieldnames'				=> true,							# Whether to show the underlying fieldnames in the e-mail output type
		'confirmationEmailIntroductoryText'	=> '',								# Introductory text for confirmation e-mail output type
		'callback'							=> false,							# Callback function (string name) (NB cannot be $this->methodname) with one integer parameter, so be called just before emitting form HTML - -1 is errors on form, 0 is blank form, 1 is result presentation if any (not called at all if form not displayed)
		'databaseConnection'				=> false,							# Database connection (filename/array/object/resource)
		'truncate'							=> false,							# Whether to truncate the visible part of a widget (global setting)
		'listUnzippedFilesMaximum'			=> 5,								# When auto-unzipping an uploaded zip file, the maximum number of files contained that should be listed (beyond this, just 'x files' will be shown) in any visible result output
		'fixMailHeaders'					=> false,							# Whether to add additional mail headers, for use with a server that fails to add Message-Id/Date/Return-Path; set as (bool) true or (str) application name
		'cols'								=> 30,								# Global setting for textarea cols - number of columns
		'rows'								=> 5,								# Global setting for textarea cols - number of rows
		'mailAdminErrors'					=> false,							# Whether to mail the admin with any errors in the form setup
		'attachments'						=> false,							# Whether to send uploaded file(s) as attachment(s) (they will not be unzipped)
		'attachmentsMaxSize'				=> '10M',							# Total maximum attachment(s) size; attachments will be allowed into an e-mail until they reach this limit
		'attachmentsDeleteIfMailed'			=> true,							# Whether to delete the uploaded file(s) if successfully mailed
		'csvBom'							=> true,							# Whether to write a BOM at the start of a CSV file
		'ip'								=> true,							# Whether to expose the submitter's IP address in the e-mail output format
		'browser'							=> false,							# Whether to expose the submitter's browser (user-agent) string in the e-mail output format
		'passwordGeneratedLength'			=> 6,								# Length of a generated password
		'antispam'							=> false,							# Global setting for anti-spam checking
		'antispamRegexp'					=> '~(a href=|<a |<script|<url|\[link|\[url|Content-Type:)~DsiU',	# Regexp for antispam, in preg_match format
		'directoryPermissions'				=> 0775,							# Permission setting used for creating new directories
		'prefixedGroupsFilterEmpty'			=> false,							# Whether to filter out empty groups when using group prefixing in dataBinding; currently limited to detecting scalar types only
	);
	
	
	## Load initial state and assign settings ##
	
	/**
	 * Constructor
	 * @param array $arguments Settings
	 */
	#!# Change this to the PHP5 __construct syntax
	function form ($suppliedArguments = array ())
	{
		# Load the application support library which itself requires the pureContent framework file, pureContent.php; this will clean up $_SERVER
		require_once ('application.php');
		
		# Assign constants
		$this->timestamp = date ('Y-m-d H:i:s');
		
		# Import supplied arguments to assign defaults against specified ones available
		foreach ($this->argumentDefaults as $argument => $defaultValue) {
			$this->settings[$argument] = (isSet ($suppliedArguments[$argument]) ? $suppliedArguments[$argument] : $defaultValue);
		}
		
		# Set up and check any database connection
		$this->_setupDatabaseConnection ();
		
		# Determine the method in use
		$this->method = ($this->settings['get'] ? 'get' : 'post');
		
		# Define the submission location (as _SERVER cannot be set in a class variable declaration); PATH_INFO attacks (see: http://forum.hardened-php.net/viewtopic.php?id=20 ) are not relevant here for this form usage
		if ($this->settings['submitTo'] === false) {$this->settings['submitTo'] = ($this->method == 'get' ? $_SERVER['SCRIPT_NAME'] : $_SERVER['REQUEST_URI']);}
		
		# Ensure the userlist is an array, whether empty or otherwise
		$this->settings['validUsers'] = application::ensureArray ($this->settings['validUsers']);
		
		# If no user is supplied, attempt to obtain the REMOTE_USER (if one exists) as the default
		if (!$this->settings['user']) {$this->settings['user'] = (isSet ($_SERVER['REMOTE_USER']) ? $_SERVER['REMOTE_USER'] : false);}
		
		# Determine the variables collection in use - $_GET or $_POST
		$this->collection = ($this->method == 'get' ? $_GET : $_POST);
		
		# If there are files posted, merge the multi-part posting of files to the main form array, effectively emulating the structure of a standard form input type
		$this->mergeFilesIntoPost ();
		
		# Assign whether the form has been posted or not
		$this->formPosted = ($this->settings['name'] ? (isSet ($this->collection[$this->settings['name']])) : !empty ($this->collection));
		
		# Add in the hidden security fields if required, having verified username existence if relevant; these need to go at the start so that any username is set as the key
		$this->addHiddenSecurityFields ();
		
		# Import the posted data if the form is posted; this has to be done initially otherwise the input widgets won't have anything to reference
		if ($this->formPosted) {$this->form = ($this->settings['name'] ? $this->collection[$this->settings['name']] : $this->collection);}
	}
	
	
	## Supported form widget types ##
	
	
	/**
	 * Create a standard input widget
	 * @param array $arguments Supplied arguments - see template
	 */
	function input ($suppliedArguments, $functionName = __FUNCTION__)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentDefaults = array (
			'name'					=> NULL,	# Name of the element
			'editable'				=> true,	# Whether the widget is editable (if not, a hidden element will be substituted but the value displayed)
			'title'					=> '',		# Introductory text
			'description'			=> '',		# Description text
			'append'				=> '',		# HTML appended to the widget
			'prepend'				=> '',		# HTML prepended to the widget
			'output'				=> array (),# Presentation format
			'required'				=> false,	# Whether required or not
			'enforceNumeric'		=> false,	# Whether to enforce numeric input or not (optional; defaults to false) [ignored for e-mail type]
			'size'					=> 30,		# Visible size (optional; defaults to 30)
			'minlength'				=> '',		# Minimum length (optional; defaults to no limit)
			'maxlength'				=> '',		# Maximum length (optional; defaults to no limit)
			'default'				=> '',		# Default value (optional)
			'regexp'				=> '',		# Case-sensitive regular expression against which the submission must validate
			'regexpi'				=> '',		# Case-insensitive regular expression against which the submission must validate
			'url'					=> false,	# Turns the widget into a URL field where a HEAD request is made to check that the URL exists; either true (which means 200, 302, 304) or a list like array (200, 301, 302, 304, )
			'retrieval'				=> false,	# Turns the widget into a URL field where the specified page/file is then retrieved and saved to the directory stated
			'disallow'				=> false,	# Regular expression against which the submission must not validate
			'antispam'				=> $this->settings['antispam'],		# Whether to switch on anti-spam checking
			'current'				=> false,	# List of current values against which the submitted value must not match
			'discard'				=> false,	# Whether to process the input but then discard it in the results
			'datatype'				=> false,	# Datatype used for database writing emulation (or caching an actual value)
			'confirmation'			=> false,	# Whether to generate a confirmation field
			'tabindex'				=> false,	# Tabindex if required; replace with integer between 0 and 32767 to create
			'several'				=> false,	# For e-mail types only: whether the field can accept multiple e-mail addresses (separated with space/commas)
			'_visible--DONOTUSETHISFLAGEXTERNALLY'		=> true,	# DO NOT USE - this is present for internal use only and exists prior to refactoring
		);
		
		# Add in password-specific defaults
		if ($functionName == 'password') {
			$argumentDefaults['generate'] = false;		# Whether to generate a password if no value supplied as default
			$argumentDefaults['confirmation'] = false;	# Whether to generate a second confirmation password field
		}
		
		# Add in email-specific defaults
		if ($functionName == 'email') {
			$argumentDefaults['confirmation'] = false;	# Whether to generate a second confirmation e-mail field
		} else {
			$argumentDefaults['several'] = false;	# Ensure this option is disabled for non-email types
		}
		
		# Add a regexp check if using URL handling (retrieval or URL HEAD check)
		#!# This change in v. 1.13.16 of moving this before the arguments are set, because the defaults get amended, points to the need for auditing of similar cases in case they are not being amended
		if ((isSet ($suppliedArguments['retrieval']) && $suppliedArguments['retrieval']) || (isSet ($suppliedArguments['url']) && $suppliedArguments['url'])) {
			
			# If no regexp has been set, add a basic URL syntax check
			#!# Ideally this should be replaced when multiple regexps allowed
			if (empty ($suppliedArguments['regexp']) && empty ($suppliedArguments['regexpi'])) {
				if (!extension_loaded ('openssl')) {
					$this->formSetupErrors['urlHttps'] = 'URL handling has been requested but the OpenSSL extension is not loaded, meaning that https requests will fail. Either compile in the OpenSSL module, or explicitly set the regexpi for the field.';
				} else {
					$argumentDefaults['regexpi'] = '^(http|https)://(.+)\.(.+)';
				}
			}
		}
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, $functionName);
		
		$arguments = $widget->getArguments ();
		
		# Generate an initial password if required and no default supplied
		if (($functionName == 'password') && $arguments['generate'] && !$arguments['default']) {
			$length = (is_numeric ($arguments['generate']) ? $arguments['generate'] : $this->settings['passwordGeneratedLength']);
			$arguments['default'] = application::generatePassword ($length);
		}
		
		# If the widget is not editable, fix the form value to the default
		if (!$arguments['editable']) {$this->form[$arguments['name']] = $arguments['default'];}
		
		# If a confirmation field is required, generate it (first) and convert the original one (second) to the confirmation type
		if ($arguments['confirmation'] && $arguments['editable']) {
			if (($functionName == 'password') || ($functionName == 'email')) {
				$arguments['confirmation'] = false;	// Prevent circular reference
				$this->$functionName ($arguments);
				$originalName = $arguments['name'];
				#!# Need to deny this as a valid name elsewhere
				$arguments['name'] .= '__confirmation';
				$arguments['title'] .= ' (confirmation)';
				$arguments['description'] = 'Please retype to confirm.';
				$arguments['discard'] = true;
				$this->validation ('same', array ($originalName, $arguments['name']));
			}
		}
		
		# Obtain the value of the form submission (which may be empty)
		$widget->setValue (isSet ($this->form[$arguments['name']]) ? $this->form[$arguments['name']] : '');
		
		# Handle whitespace issues
		$widget->handleWhiteSpace ();
		
		# Prevent multi-line submissions
		$widget->preventMultilineSubmissions ();
		
		# Run minlength checking
		$widget->checkMinLength ();
		
		# Run maxlength checking
		$widget->checkMaxLength ();
		
		# Perform pattern checks
		$regexpCheck = $widget->regexpCheck ();
		
		# Clean to numeric if required
		$widget->cleanToNumeric ();
		
		# Perform antispam checks
		$widget->antispamCheck ();
		
		# Perform uniqueness check
		$widget->uniquenessCheck ();
		
		$elementValue = $widget->getValue ();
		
		# Assign the initial value if the form is not posted (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid)
		if (!$this->formPosted) {$elementValue = $arguments['default'];}
		
		# Describe restrictions on the widget
		if ($arguments['enforceNumeric'] && ($functionName != 'email')) {$restriction = 'Must be numeric';}
		if ($functionName == 'email') {$restriction = 'Must be valid';}
		if (($arguments['regexp'] || $arguments['regexpi']) && ($functionName != 'email')) {$restriction = 'A specific pattern is required';}
		
		# Re-assign back the value
		$this->form[$arguments['name']] = $elementValue;
		
		# Do retrieval if required
		if (($arguments['retrieval'] || $arguments['url']) && $this->form[$arguments['name']] && !$widget->getElementProblems (false)) {
			
			# Do not use with e-mail/password types
			if ($functionName != 'input') {
				$this->formSetupErrors['urlHandlingInputOnly'] = 'URL handling can only be used on a standard input field type.';
				$arguments['retrieval'] = false;
			}
			
			# Do not use with e-mail/password types
			if (!ini_get ('allow_url_fopen')) {
				$this->formSetupErrors['urlHandlingAllowUrlFopenOff'] = 'URL handling cannot be done as the server configuration disallows external file opening.';
			}
			
			# Check that the selected directory exists and is writable (or create it)
			if ($arguments['retrieval']) {
				if (!is_dir ($arguments['retrieval'])) {
					if (!application::directoryIsWritable ($arguments['retrieval'])) {
						$this->formSetupErrors['urlHandlingDirectoryNotWritable'] = "The directory specified for the <strong>{$arguments['name']}</strong> input URL-retrieval element is not writable. Please check that the file permissions to ensure that the webserver 'user' can write to the directory.";
						$arguments['retrieval'] = false;
					} else {
						#!# Third parameter doesn't exist in PHP4 - will this cause a crash?
						umask (0);
						mkdir ($arguments['retrieval'], $this->settings['directoryPermissions'], $recursive = true);
					}
				}
			}
		}
		
		# Define the widget's core HTML
		if ($arguments['editable']) {
			$widgetHtml = '<input' . $this->nameIdHtml ($arguments['name']) . ' type="' . ($functionName == 'password' ? 'password' : 'text') . "\" size=\"{$arguments['size']}\"" . ($arguments['maxlength'] != '' ? " maxlength=\"{$arguments['maxlength']}\"" : '') . " value=\"" . htmlspecialchars ($this->form[$arguments['name']]) . '"' . $widget->tabindexHtml () . ' />';
		} else {
			$widgetHtml  = ($functionName == 'password' ? str_repeat ('*', strlen ($arguments['default'])) : htmlspecialchars ($this->form[$arguments['name']]));
			#!# Change to registering hidden internally
			$hiddenInput = '<input' . $this->nameIdHtml ($arguments['name']) . ' type="hidden" value="' . htmlspecialchars ($this->form[$arguments['name']]) . '" />';
			$widgetHtml .= $hiddenInput;
		}
		
		# Get the posted data
		if ($this->formPosted) {
			if ($functionName == 'password') {
				$data['compiled'] = $this->form[$arguments['name']];
				$data['presented'] = str_repeat ('*', strlen ($this->form[$arguments['name']]));
			} else {
				$data['presented'] = $this->form[$arguments['name']];
			}
			
			# Do URL retrieval if OK
			#!# This ought to be like doUploads, which is run only at the end
			if ($arguments['retrieval'] && $regexpCheck) {
				$saveLocation = $arguments['retrieval'] . basename ($elementValue);
				#!# This next line should be replaced with some variant of urlencode that doesn't swallow / or :
				$elementValue = str_replace (' ', '%20', $elementValue);
				if (!$fileContents = @file_get_contents ($elementValue)) {
					$elementProblems['retrievalFailure'] = "URL retrieval failed; possibly the URL you quoted does not exist, or the server is blocking file downloads somehow.";
				} else {
					file_put_contents ($saveLocation, $fileContents);
				}
			}
			
			# Do URL HEAD request if required and if the regexp check has passed
			if ($arguments['url'] && $regexpCheck) {
				$urlOk = false;
				$response = false;
				if ($headers = get_headers ($elementValue)) {
					$response = $headers[0];
					if (preg_match ('/ ([0-9]+) /', $response, $matches)) {
						$httpResponse = $matches[1];
						$validResponses = (is_array ($arguments['url']) ? $arguments['url'] : array (200 /* OK */, 302 /* Found */, 304 /* Not Modified */));	// See http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html for responses
						if (in_array ($httpResponse, $validResponses)) {
							$urlOk = true;
						}
					}
				}
				if (!$urlOk) {
					$elementProblems['urlFailure'] = "URL check failed; possibly the URL you quoted does not exist or has a redirection in place." . ($response ? ' The response from the site was: <em>' . htmlspecialchars ($response) . '</em>.' : '') . ' Please check the URL carefully and retry.';
				}
			}
		}
		
		# Add the widget to the master array for eventual processing
		$this->elements[$arguments['name']] = array (
			'type' => $functionName,
			'html' => $arguments['prepend'] . $widgetHtml . $arguments['append'],
			'title' => $arguments['title'],
			'description' => $arguments['description'],
			'restriction' => (isSet ($restriction) && $arguments['editable'] ? $restriction : false),
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $arguments['required'],
			'requiredButEmpty' => $widget->requiredButEmpty (),
			'suitableAsEmailTarget' => ($functionName == 'email'),
			'output' => $arguments['output'],
			'discard' => $arguments['discard'],
			'data' => (isSet ($data) ? $data : NULL),
			'datatype' => ($arguments['datatype'] ? $arguments['datatype'] : "`{$arguments['name']}` " . 'VARCHAR(' . ($arguments['maxlength'] ? $arguments['maxlength'] : '255') . ')') . ($arguments['required'] ? ' NOT NULL' : '') . " COMMENT '" . (addslashes ($arguments['title'])) . "'",
			'groupValidation' => ($functionName == 'password' ? 'compiled' : false),
		);
		
		#!# Temporary hacking to add hidden widgets when using the _hidden type in dataBinding
		if (!$arguments['_visible--DONOTUSETHISFLAGEXTERNALLY']) {
			$this->elements[$arguments['name']]['_visible--DONOTUSETHISFLAGEXTERNALLY'] = $hiddenInput;
		}
	}
	
	
	/**
	 * Create a password widget (same as an input widget but using the HTML 'password' type)
	 * @param array $arguments Supplied arguments same as input type
	 */
	function password ($suppliedArguments)
	{
		# Pass through to the standard input widget, but in password mode
		$this->input ($suppliedArguments, 'password');
	}
	
	
	/**
	 * Create an input field requiring a syntactically valid e-mail address; if a more specific e-mail validation is required, use $form->input and supply an e-mail validation regexp
	 * @param array $arguments Supplied arguments same as input type, but enforceNumeric and regexp ignored
	 */
	function email ($suppliedArguments)
	{
		# Pass through to the standard input widget, but in password mode
		$this->input ($suppliedArguments, 'email');
	}
	
	
	/**
	 * Create a textarea box
	 * @param array $arguments Supplied arguments - see template
	 */
	function textarea ($suppliedArguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentDefaults = array (
			'name'					=> NULL,	# Name of the element
			'editable'				=> true,	# Whether the widget is editable (if not, a hidden element will be substituted but the value displayed)
			'title'					=> '',		# Introductory text
			'description'			=> '',		# Description text
			'append'				=> '',		# HTML appended to the widget
			'prepend'				=> '',		# HTML prepended to the widget
			'output'				=> array (),# Presentation format
			'required'				=> false,	# Whether required or not
			'enforceNumeric'		=> false,	# Whether to enforce numeric input or not (optional; defaults to false)
			'cols'					=> $this->settings['cols'],		# Number of columns (optional; defaults to 30)
			'rows'					=> $this->settings['rows'],		# Number of rows (optional; defaults to 5)
			'wrap'					=> false,	# Value for non-standard 'wrap' attribute
			'default'				=> '',		# Default value (optional)
			'regexp'				=> '',		# Case-sensitive regular expression(s) against which all lines of the submission must validate
			'regexpi'				=> '',		# Case-insensitive regular expression(s) against which all lines of the submission must validate
			'disallow'				=> false,	# Regular expression against which all lines of the submission must not validate
			'antispam'				=> $this->settings['antispam'],		# Whether to switch on anti-spam checking
			'current'				=> false,	# List of current values which the submitted value must not match
			'discard'				=> false,	# Whether to process the input but then discard it in the results
			'mode'					=> 'normal',	# Special mode: normal/lines/coordinates
			'editable'				=> true,	# Whether the widget is editable (if not, a hidden element will be substituted but the value displayed)
			'datatype'				=> false,	# Datatype used for database writing emulation (or caching an actual value)
			'minlength'				=> false,	# Minimum number of characters allowed
			'maxlength'				=> false,	# Maximum number of characters allowed
			'tabindex'				=> false,	# Tabindex if required; replace with integer between 0 and 32767 to create
		);
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, __FUNCTION__);
		
		$arguments = $widget->getArguments ();
		
		# If the widget is not editable, fix the form value to the default
		if (!$arguments['editable']) {$this->form[$arguments['name']] = $arguments['default'];}
		
		# Obtain the value of the form submission (which may be empty)
		$widget->setValue (isSet ($this->form[$arguments['name']]) ? $this->form[$arguments['name']] : '');
		
		# Handle whitespace issues
		#!# Policy issue of whether this should apply on a per-line basis
		$widget->handleWhiteSpace ();
		
		# Clean to numeric if required
		$widget->cleanToNumeric ();
		
		# Enable minlength checking
		#!# A $restriction needs to be shown
		$widget->checkMinLength ();
		
		# Enable maxlength checking
		#!# A $restriction needs to be shown
		$widget->checkMaxLength ();
		
		# Check whether the field satisfies any requirement for a field to be required
		$requiredButEmpty = $widget->requiredButEmpty ();
		
		# Perform uniqueness check
		$widget->uniquenessCheck ();
		
		# Perform antispam checks
		$widget->antispamCheck ();
		
		$elementValue = $widget->getValue ();
		
		# Perform validity tests if anything has been submitted and regexp(s)/disallow are supplied
		#!# Refactor into the widget class by adding multiline capability
		if ($elementValue && ($arguments['regexp'] || $arguments['regexpi'] || $arguments['disallow'] || $arguments['mode'] == 'coordinates')) {
			
			# Branch a copy of the data as an array, split by the newline and check it is complete
			$lines = explode ("\n", $elementValue);
			
			# Split each line into two fields and loop through each line to deal with a mid-line split
			$i = 0;
			foreach ($lines as $line) {
				$i++;
				
				# Trim each line for testing
				$line = trim ($line);
				
				# Add a test for whitespace in coordinates mode
				if ($arguments['mode'] == 'coordinates') {
					if (!preg_match ("/\s/i", $line)) {
						$problemLines[] = $i;
						continue;
					}
				}
				
				# If the line does not validate against a specified regexp, add the line to a list of lines containing a problem then move onto the next line
				if ($arguments['regexp'] || $arguments['regexpi']) {
					if ($arguments['regexp'] && (!application::pereg ($arguments['regexp'], $line))) {
						$problemLines[] = $i;
						continue;
					} else if ($arguments['regexpi'] && (!application::peregi ($arguments['regexpi'], $line))) {
						$problemLines[] = $i;
						continue;
					}
				}
				
				# If the line does not validate against a specified disallow, add the line to a list of lines containing a problem then move onto the next line
				#!# Merge this with formWidget::regexpCheck ()
				#!# Consider allowing multiple disallows, even though a regexp can deal with that anyway
				if ($arguments['disallow']) {
					$disallowRegexp = $arguments['disallow'];
					if (is_array ($arguments['disallow'])) {
						foreach ($arguments['disallow'] as $disallowRegexp => $disallowErrorMessage) {
							break;
						}
					}
					if (application::pereg ($disallowRegexp, $line)) {
						$disallowProblemLines[] = $i;
						continue;
					}
				}
			}
			
			# If any problem lines are found, construct the error message for this
			if (isSet ($problemLines)) {
				$elementProblems['failsRegexp'] = (count ($problemLines) > 1 ? 'Rows ' : 'Row ') . implode (', ', $problemLines) . (count ($problemLines) > 1 ? ' do not' : ' does not') . ' match a specified pattern required for this section' . (($arguments['mode'] == 'coordinates') ? ', ' . (($arguments['regexp'] || $arguments['regexpi']) ? 'including' : 'namely' ) . ' the need for two co-ordinates per line' : '') . '.';
			}
			
			# If any problem lines are found, construct the error message for this
			if (isSet ($disallowProblemLines)) {
				$elementProblems['failsDisallow'] = (isSet ($disallowErrorMessage) ? $disallowErrorMessage : (count ($disallowProblemLines) > 1 ? 'Rows ' : 'Row ') . implode (', ', $disallowProblemLines) . (count ($disallowProblemLines) > 1 ? ' match' : ' matches') . ' a specified disallowed pattern for this section.');
			}
		}
		
		# Assign the initial value if the form is not posted (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid)
		if (!$this->formPosted) {$elementValue = $arguments['default'];}
		
		# Describe restrictions on the widget
		#!# Regexp not being listed
		switch ($arguments['mode']) {
			case 'lines':
				$restriction = 'Must have one numeric item per line';
				break;
			case 'coordinates':
				$restriction = 'Must have two numeric items (x,y) per line';
				break;
		}
		
		# Re-assign back the value
		$this->form[$arguments['name']] = $elementValue;
		
		# Define the widget's core HTML
		if ($arguments['editable']) {
			$widgetHtml = '<textarea' . $this->nameIdHtml ($arguments['name']) . " cols=\"{$arguments['cols']}\" rows=\"{$arguments['rows']}\"" . ($arguments['wrap'] ? " wrap=\"{$arguments['wrap']}\"" : '') . $widget->tabindexHtml () . '>' . htmlspecialchars ($this->form[$arguments['name']]) . '</textarea>';
		} else {
			$widgetHtml  = str_replace ("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', nl2br (htmlspecialchars ($this->form[$arguments['name']])));
			$widgetHtml .= '<input' . $this->nameIdHtml ($arguments['name']) . ' type="hidden" value="' . htmlspecialchars ($this->form[$arguments['name']]) . '" />';
		}
		
		# Get the posted data
		if ($this->formPosted) {
			
			# For presented, assign the raw data directly to the output array
			$data['presented'] = $this->form[$arguments['name']];
			
			# For raw components:
			switch ($arguments['mode']) {
					case 'coordinates':
					
					# For the raw components version, split by the newline then by the whitespace (ensuring that whitespace exists, to prevent undefined offsets), presented as an array (x, y)
					$lines = explode ("\n", $this->form[$arguments['name']]);
					foreach ($lines as $autonumber => $line) {
						if (!substr_count ($line, ' ')) {$line .= ' ';}
						list ($data['rawcomponents'][$autonumber]['x'], $data['rawcomponents'][$autonumber]['y']) = explode (' ', $line);
						ksort ($data['rawcomponents'][$autonumber]);
					}
					break;
				case 'lines':
					# For the raw components version, split by the newline
					$data['rawcomponents'] = explode ("\n", $this->form[$arguments['name']]);
					break;
					
				default:
					# Assign the raw data directly to the output array
					$data['rawcomponents'] = $this->form[$arguments['name']];
			}
		}
		
		# Add the widget to the master array for eventual processing
		$this->elements[$arguments['name']] = array (
			'type' => __FUNCTION__,
			'html' => $arguments['prepend'] . $widgetHtml . $arguments['append'],
			'title' => $arguments['title'],
			'description' => $arguments['description'],
			'restriction' => (isSet ($restriction) && $arguments['editable'] ? $restriction : false),
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $arguments['required'],
			'requiredButEmpty' => $requiredButEmpty,
			'suitableAsEmailTarget' => false,
			'output' => $arguments['output'],
			'discard' => $arguments['discard'],
			'data' => (isSet ($data) ? $data : NULL),
			'datatype' => ($arguments['datatype'] ? $arguments['datatype'] : "`{$arguments['name']}` " . 'BLOB') . ($arguments['required'] ? ' NOT NULL' : '') . " COMMENT '" . (addslashes ($arguments['title'])) . "'",
		);
	}
	
	
	/**
	 * Create a rich text editor field based on FCKeditor
	 * @param array $arguments Supplied arguments - see template
	 */
	 
	/*
	
	# Note: make sure php_value file_uploads is on in the upload location!
	
	The following source code alterations must be made to FCKeditor 2.6
	
	1. Add public patches providing increased control of FCKeditor uploading (note that these two clash in one place which will need manual resolution)
	Apply the patch (or changed files) which someone has supplied at: http://dev.fckeditor.net/ticket/1650 which provides upload filename regexp checking
	Apply the patch (or changed files) which someone has supplied at: http://dev.fckeditor.net/ticket/1651 which provides upload filename clash configuration
	
	2. Customised configurations which cannot go in the PHP at present
	Add the supplied file <fckeditor-root>/fckconfig-customised.js
	
	3. Open <fckeditor-root>editor/filemanager/browser/connectors/php/config.php and add to the end:
		
		# Check for regexp [available from patch in ticket 1650]
		$Config['Regexp']['File']	= '^([-_a-zA-Z0-9]{1,40})$' ;
		$Config['Regexp']['Image']	= '^([-_a-zA-Z0-9]{1,40})$' ;
		$Config['Regexp']['Flash']	= '^([-_a-zA-Z0-9]{1,40})$' ;
		$Config['Regexp']['Media']	= '^([-_a-zA-Z0-9]{1,40})$' ;
		
		# Clash checking [available from patch in ticket 1651]
		$Config['FilenameClashBehaviour'] = 'renameold';
		
		# Security
		$Config['ChmodOnUpload'] = 0770 ;
		$Config['ChmodOnFolderCreate'] = 0770 ;
		
		# Local settings, which will override the main ones above
		$Config['Enabled'] = true ;
		$Config['UserFilesPath'] = '/' ;	// Set to / if you want filebrowsing across the whole site directory
		$Config['UserFilesAbsolutePath'] = $_SERVER['DOCUMENT_ROOT'];
		
		$Config['FileTypesPath']['File']			= $Config['UserFilesPath'];
		$Config['QuickUploadPath']['File']			= $Config['UserFilesPath'];
		$Config['FileTypesPath']['Image']			= $Config['UserFilesPath'];
		$Config['QuickUploadPath']['Image']			= $Config['UserFilesPath'] . 'images/';
		$Config['FileTypesPath']['Flash']			= $Config['UserFilesPath'];
		$Config['QuickUploadPath']['Flash']			= $Config['UserFilesPath'];
		$Config['FileTypesPath']['Media']			= $Config['UserFilesPath'];
		$Config['QuickUploadPath']['Media']			= $Config['UserFilesPath'];
		
		$Config['FileTypesAbsolutePath']['File']			= $Config['UserFilesAbsolutePath'];
		$Config['QuickUploadAbsolutePath']['File']			= $Config['UserFilesAbsolutePath'];
		$Config['FileTypesAbsolutePath']['Image']			= $Config['UserFilesAbsolutePath'];
		$Config['QuickUploadAbsolutePath']['Image']			= $Config['UserFilesAbsolutePath'] . 'images/';
		$Config['FileTypesAbsolutePath']['Flash']			= $Config['UserFilesAbsolutePath'];
		$Config['QuickUploadAbsolutePath']['Flash']			= $Config['UserFilesAbsolutePath'];
		$Config['FileTypesAbsolutePath']['Media']			= $Config['UserFilesAbsolutePath'];
		$Config['QuickUploadAbsolutePath']['Media']			= $Config['UserFilesAbsolutePath'];
	
	
	FCKeditor 2.6 problems:
	- Auto-hyperlinking doesn't work in Firefox - see http://dev.fckeditor.net/ticket/302
	- CSS underlining inheritance seems wrong in Firefox See: http://dev.fckeditor.net/ticket/303
	- Can't set file browser startup folder; see http://dev.fckeditor.net/ticket/1652
	- ToolbarSets all have to be set in JS and cannot be done via PHP - see http://dev.fckeditor.net/ticket/30
	- FormatIndentator = "\t" - has to be set at JS level - see http://dev.fckeditor.net/ticket/304
	- Replacing the above manual patches with the results of http://dev.fckeditor.net/ticket/1650 and http://dev.fckeditor.net/ticket/1651
	- Single file for file browser configuration: http://dev.fckeditor.net/ticket/845
	- Image manager needs thumbnail/resize/rename functionality: http://dev.fckeditor.net/ticket/147
	- Start editor in source mode: http://dev.fckeditor.net/ticket/593
	
	*/
	
	function richtext ($suppliedArguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentDefaults = array (
			'name'					=> NULL,	# Name of the element
			'editable'				=> true,	# Whether the widget is editable (if not, a hidden element will be substituted but the value displayed)
			'title'					=> NULL,		# Introductory text
			'description'			=> '',		# Description text
			'append'				=> '',		# HTML appended to the widget
			'prepend'				=> '',		# HTML prepended to the widget
			'output'				=> array (),# Presentation format
			'required'				=> false,	# Whether required or not
			'regexp'				=> '',		# Case-sensitive regular expression against which the submission must validate
			'regexpi'				=> '',		# Case-insensitive regular expression against which the submission must validate
			'disallow'				=> false,		# Regular expression against which the submission must not validate
			'current'				=> false,	# List of current values which the submitted value must not match
			'discard'				=> false,	# Whether to process the input but then discard it in the results
			'width'					=> '100%',		# Width
			'height'				=> '400px',		# Height
			'default'				=> '',		# Default value (optional)
			'datatype'				=> false,	# Datatype used for database writing emulation (or caching an actual value)
			'editorBasePath'		=> '/_fckeditor/',	# Location of the editor files
			'editorToolbarSet'		=> 'pureContent',	# Editor toolbar set
			'CKFinder'						=> false,	// Whether to use CKFinder or the standard finder
			'editorConfig'				=> array (	# Editor configuration - see http://wiki.fckeditor.net/Developer's_Guide/Configuration/Configurations_Settings
				'CustomConfigurationsPath'	=> '/_fckeditor/fckconfig-customised.js',
				'FontFormats'				=> 'p;h1;h2;h3;h4;h5;h6;pre',
				'EditorAreaCSS'				=> '',
				'StartupFocus'				=> false,
				'ToolbarCanCollapse'		=> false,
				'LinkUpload'				=> false,	// Whether the link box includes the [quick]'upload' tab
				'ImageUpload'				=> false,	// Whether the image box includes the [quick]'upload' tab
				'BodyId'					=> false,	// Apply value of <body id="..."> to editing window
				'BodyClass'					=> false,	// Apply value of <body class="..."> to editing window
				'CleanWordKeepsStructure'	=> true,	// Use Word structure rather than presentation
				'LinkDlgHideTarget'			=> true,	// Hide link target dialog box
				'FillEmptyBlocks'			=> false,	// Whether to add &nbsp; into empty table cells
				'FirefoxSpellChecker'		=> true,	// Enable Firefox 2's spell checker
				'ForcePasteAsPlainText'		=> false,	// Discard all formatting when pasting text
				'BaseHref'					=> $_SERVER['_PAGE_URL'],		// Current location (enables relative images to be correct)
				'CKFinderLinkBrowserURL'	=> '/_ckfinder/ckfinder.html',
				'CKFinderImageBrowserURL'	=> '/_ckfinder/ckfinder.html',
				'CKFinderFlashBrowserURL'	=> '/_ckfinder/ckfinder.html',
				'CKFinderAccessControl'		=> false,	// Access Control List (ACL) passed to CKFinder in the format it requires - false to disable or an array (empty/populated) to enable
				'CKFinderStartupPath'		=> false,		// CKFinder startup path, or false to disable
				//'FormatIndentator'			=> '	', // Tabs - still doesn't work in FCKeditor
				// "ToolbarSets['pureContent']" => "[ ['Source'], ['Cut','Copy','Paste','PasteText','PasteWord','-','SpellCheck'], ['Undo','Redo','-','Find','Replace','-','SelectAll','RemoveFormat'], ['Bold','Italic','StrikeThrough','-','Subscript','Superscript'], ['OrderedList','UnorderedList','-','Outdent','Indent'], ['Link','Unlink','Anchor'], ['Image','Table','Rule','SpecialChar'/*,'ImageManager','UniversalKey'*/], /*['Form','Checkbox','Radio','Input','Textarea','Select','Button','ImageButton','Hidden']*/ [/*'FontStyleAdv','-','FontStyle','-',*/'FontFormat','-','-'], ['Print','About'] ] ;",
			),
			'protectEmailAddresses' => true,	// Whether to obfuscate e-mail addresses
			'externalLinksTarget'	=> '_blank',	// The window target name which will be instanted for external links (as made within the editing system) or false
			'directoryIndex' => 'index.html',		// Default directory index name
			'imageAlignmentByClass'	=> true,		// Replace align="foo" with class="foo" for images
			'nofixTag'	=> '<!-- nofix -->',	// Special marker which indicates that the HTML should not be cleaned (or false to disable)
			'replacements' => array (),	// Regexp replacements to add before standard replacements are done
		);
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, __FUNCTION__, $subargument = 'editorConfig');
		
		$arguments = $widget->getArguments ();
		
		# If the widget is not editable, fix the form value to the default
		if (!$arguments['editable']) {$this->form[$arguments['name']] = $arguments['default'];}
		
		# Obtain the value of the form submission (which may be empty)
		$widget->setValue (isSet ($this->form[$arguments['name']]) ? $this->form[$arguments['name']] : '');
		
		# Handle whitespace issues
		$widget->handleWhiteSpace ();
		
		# Perform pattern checks
		$widget->regexpCheck ();
		
		# Check whether the field satisfies any requirement for a field to be required
		$requiredButEmpty = $widget->requiredButEmpty ();
		
		# Perform uniqueness check
		$widget->uniquenessCheck ();
		
		$elementValue = $widget->getValue ();
		
		# Assign the initial value if the form is not posted (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid), or clean it if posted
		$elementValue = (!$this->formPosted ? $arguments['default'] : $this->richtextClean ($this->form[$arguments['name']], $arguments, $arguments['nofixTag']));
		
		# Define the widget's core HTML
		if ($arguments['editable']) {
			
			# Determine whether to use CKFinder
			if ($arguments['CKFinder']) {
				$arguments['editorConfig']['LinkBrowserURL'] = $arguments['editorConfig']['CKFinderLinkBrowserURL'];
				$arguments['editorConfig']['ImageBrowserURL'] = $arguments['editorConfig']['CKFinderImageBrowserURL'];
				$arguments['editorConfig']['FlashBrowserURL'] = $arguments['editorConfig']['CKFinderFlashBrowserURL'];
				unset ($arguments['editorConfig']['CKFinderLinkBrowserURL']);
				unset ($arguments['editorConfig']['CKFinderImageBrowserURL']);
				unset ($arguments['editorConfig']['CKFinderFlashBrowserURL']);
				
				# Use the ACL functionality if required, by writing it into the session
				#!# Ideally, CKFinder would have a better way of providing a configuration directly, or pureContentEditor could have a callback that is queried, but this would mean changing all cases of 'echo' and have a non-interactive mode setting in the constructor call
				if (is_array ($arguments['editorConfig']['CKFinderAccessControl'])) {
					if (!isset ($_SESSION)) {session_start ();}
					$_SESSION['CKFinderAccessControl'] = $arguments['editorConfig']['CKFinderAccessControl'];
				}
				
				# Use the startup path functionality if required, by writing it into the session
				#!# Not currently supported in ckfinder_1.3-patched/config.php
				if ($arguments['editorConfig']['CKFinderStartupPath'] !== false) {
					if (!isset ($_SESSION)) {session_start ();}
					$_SESSION['CKFinderStartupPath'] = $arguments['editorConfig']['CKFinderStartupPath'];
				}
			}
			
			# Define the widget's core HTML by instantiating the richtext editor module and setting required options
			require_once ('fckeditor.php');
			$editor = new FCKeditor ($this->settings['name'] ? "{$this->settings['name']}[{$arguments['name']}]" : $arguments['name']);
			#!# NB Can't define ID in FCKeditor textarea
			$editor->BasePath	= $arguments['editorBasePath'];
			$editor->Width		= $arguments['width'];
			$editor->Height		= $arguments['height'];
			$editor->ToolbarSet	= $arguments['editorToolbarSet'];
			$editor->Value		= $elementValue;
			$editor->Config		= $arguments['editorConfig'];
			$widgetHtml = $editor->CreateHtml ();
		} else {
			$widgetHtml = $this->form[$arguments['name']];
			$widgetHtml .= '<input' . $this->nameIdHtml ($arguments['name']) . ' type="hidden" value="' . htmlspecialchars ($this->form[$arguments['name']]) . '" />';
		}
		
		# Re-assign back the value
		$this->form[$arguments['name']] = $elementValue;
		
		# Get the posted data
		if ($this->formPosted) {
			
			# Get the data
			$data['presented'] = $elementValue;
		}
		
		# Add the widget to the master array for eventual processing
		$this->elements[$arguments['name']] = array (
			'type' => __FUNCTION__,
			'html' => $arguments['prepend'] . $widgetHtml . $arguments['append'],
			'title' => $arguments['title'],
			'description' => $arguments['description'],
			'restriction' => (isSet ($restriction) && $arguments['editable'] ? $restriction : false),
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $arguments['required'],
			'requiredButEmpty' => $requiredButEmpty,
			'suitableAsEmailTarget' => false,
			'output' => $arguments['output'],
			'discard' => $arguments['discard'],
			'data' => (isSet ($data) ? $data : NULL),
			'datatype' => ($arguments['datatype'] ? $arguments['datatype'] : "`{$arguments['name']}` " . 'TEXT') . ($arguments['required'] ? ' NOT NULL' : '') . " COMMENT '" . (addslashes ($arguments['title'])) . "'",
		);
	}
	
	
	# Function to clean the content
	function richtextClean ($content, &$arguments, $nofixTag = '<!-- nofix -->', $charset = 'utf8')
	{
		# Determine whether the <!-- nofix --> tag is present at the start and therefore whether the content should be cleaned
		$nofixPresent = ($nofixTag && (substr ($content, 0, strlen ($nofixTag)) == $nofixTag));	// ereg/preg_match are not used as otherwise escaping may be needed
		$cleanHtml = !$nofixPresent;
		
		# Cache wanted characters stripped by tidy's 'bare' option
		$cache = array (
			'&#8211;' => '__NDASH',
			'&#8212;' => '__MDASH',
			'&ndash;' => '__NDASH',
			'&mdash;' => '__MDASH',
			'XXX' => 'YYY',
			'<p style="clear: both;">' => '__PSTYLECLEARBOTH',
			'<p style="clear: left;">' => '__PSTYLECLEARLEFT',
			'<p style="clear: right;">' => '__PSTYLECLEARRIGHT',
		);
		if ($cleanHtml) {
			$content = str_replace (array_keys ($cache), array_values ($cache), $content);
		}
		
		# If the tidy extension is not available (e.g. PHP4), perform cleaning with the Tidy API
		if ($cleanHtml && function_exists ('tidy_parse_string')) {
			
			# Set options, as at http://tidy.sourceforge.net/docs/quickref.html
			$parameters = array (
				'output-xhtml' => true,
				'show-body-only'	=> true,
				'clean' => true,	// Note that this also removes style="clear: ..." from e.g. a <p> tag
				'enclose-text'	=> true,
				'drop-proprietary-attributes' => true,
				'drop-font-tags' => true,
				'drop-empty-paras' => true,
				'hide-comments' => true,
				'join-classes' => true,
				'join-styles' => true,
				'logical-emphasis' => true,
				'merge-divs'	=> false,
				'word-2000'	=> true,
				'indent'	=> false,
				'indent-spaces'	=> 4,
				'wrap'	=> 0,
				'fix-backslash'	=> false,
				'force-output'	=> true,
				'bare'	=> true,	// Note: this replaces &ndash; and &mdash; hence they are cached above
			);
			
			# Tidy up the output; see http://www.zend.com/php5/articles/php5-tidy.php for a tutorial
			$content = tidy_parse_string ($content, $parameters, $charset);
			tidy_clean_repair ($content);
			$content = tidy_get_output ($content);
		}
		
		# Resubstitute the cached items
		if ($cleanHtml) {
			$content = str_replace (array_values ($cache), array_keys ($cache), $content);
		}
		
		# Start an array of regexp replacements
		$replacements = $arguments['replacements'];	// By default an empty array
		
		# Protect e-mail spanning from later replacement in the main regexp block
		if ($arguments['protectEmailAddresses']) {
			$replacements += array (
				'<span>@</span>' => '<TEMPspan>@</TEMPspan>',
			);
		}
		
		# Define main regexp replacements
		if ($cleanHtml) {
			$replacements += array (
				'<\?xml:namespace([^>]*)>' => '',	// Remove Word XML namespace tags
				'<o:p> </o:p>'	=> '',	// WordHTML characters
				'<o:p></o:p>'	=> '',	// WordHTML characters
				'<o:p />'	=> '',	// WordHTML characters
				' class="c([0-9])"'     => '',  // Word classes
				'<p> </p>'      => '',  // Empty paragraph
				'<div> </div>'  => '',  // Empty divs
				'<span>([^<]*)</span>' => '<TEMP2span>\\1</TEMP2span>',	// Protect FIR-style spans
				"</?span([^>]*)>"	=> '',	// Remove other spans
				'\s*<h([1-6]{1})([^>]*)>\s</h([1-6]{1})>\s*' => '',	// Headings containing only whitespace
				'\s+</li>'     => '</li>',     // Whitespace before list item closing tags
				'\s+</h'       => '</h',       // Whitespace before heading closing tags
				'<h([2-6]+)'	=> "\n<h\\1",	// Line breaks before headings 2-6
				'<br /></h([1-6]+)>'	=> "</h\\1>",	// Pointless line breaks just before a heading closing tag
				'</h([1-6]+)>'	=> "</h\\1>\n",	// Line breaks after all headings
				"<(li|tr|/tr|tbody|/tbody)"	=> "\t<\\1",	// Indent level-two tags
				"<td"	=> "\t\t<td",	// Double-indent level-three tags
				'<h([1-6]+) id="Heading([0-9]+)">'      => '<h\\1>',    // Headings from R2Net converter
			);
		}
		
		# Non- HTML-cleaning replacements
		$replacements += array (
			" href=\"{$arguments['editorBasePath']}editor/"	=> ' href=\"',	// Workaround for Editor basepath bug
			' href="([^"]*)/' . $arguments['directoryIndex'] . '"'	=> ' href="\1/"',	// Chop off directory index links
		);
		
		# Obfuscate e-mail addresses
		if ($arguments['protectEmailAddresses']) {
			$replacements += array (
				'<TEMPspan>@</TEMPspan>' => '<span>&#64;</span>',
				'<TEMP2span>([^<]*)</TEMP2span>' => '<span>\\1</span>',	// Replace FIR-style spans back
				'<a([^>]*) href="([^("|@)]+)@([^"]+)"([^>]*)>' => '<a\1 href="mailto:\2@\3"\4>',	// Initially catch badly formed HTML versions that miss out mailto: (step 1)
				'<a href="mailto:mailto:' => '<a href="mailto:',	// Initially catch badly formed HTML versions that miss out mailto: (step 2)
				'<a([^>]*) href="mailto:([^("|@)]+)@([^"]+)"([^>]*)>([^(@|<)]+)@([^<]+)</a>' => '\5<span>&#64;</span>\6',
				'<a([^>]*) href="mailto:([^("|@)]+)@([^"]+)"([^>]*)>([^<]*)</a>' => '\5 [\2<span>&#64;</span>\3]',
				'<span>@</span>' => '<span>&#64;</span>',
				'<span><span>&#64;</span></span>' => '<span>&#64;</span>',
				'([_a-z0-9-]+)(\.[_a-z0-9-]+)*@([a-z0-9-]+)(\.[a-z0-9-]+)*(\.[a-z]{2,6})' => '\1\2<span>&#64;</span>\3\4\5', // Non-linked, standard text, addresses
			);
		}
		
		# Ensure links to pages outside the page are in a new window
		if ($cleanHtml && $arguments['externalLinksTarget']) {
			$replacements += array (
				'<a target="([^"]*)" href="([^"]*)"([^>]*)>' => '<a href="\2" target="\1"\3>',	// Move existing target to the end
				'<a href="(http:|https:)//([^"]*)"([^>]*)>' => '<a href="\1//\2" target="' . $arguments['externalLinksTarget'] . '"\3>',	// Add external links
				'<a href="([^"]*)" target="([^"]*)" target="([^"]*)"([^>]*)>' => '<a href="\1" target="\2"\4>',	// Remove any duplication
			);
		}
		
		# Replacement of image alignment with a similarly-named class
		if ($cleanHtml && $arguments['imageAlignmentByClass']) {
			$replacements += array (
				'<img([^>]*) align="(left|middle|center|centre|right)" ([^>]*)class="([^"]*)"([^>]*)>' => '<img\1 class="\4 \2"\5 \3>',
				'<img([^>]*) class="([^"]*)" ([^>]*)align="(left|middle|center|centre|right)"([^>]*)>' => '<img\1 class="\2 \4"\5 \3>',
				'<img([^>]*) align="(left|middle|center|centre|right)"([^>]*)>' => '<img\1 class="\2"\3>',
			);
		}
		
		# Perform the replacements
		foreach ($replacements as $find => $replace) {
			#!# Migrate to direct preg_replace
			$content = application::peregi_replace ($find, $replace, $content);
		}
		
		# Return the tidied and adjusted content
		return $content;
	}
	
	
	/**
	 * Create a select (drop-down) box widget
	 * @param array $arguments Supplied arguments - see template
	 */
	function select ($suppliedArguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentDefaults = array (
			'name'					=> NULL,	# Name of the element
			'editable'				=> true,	# Whether the widget is editable (if not, a hidden element will be substituted but the value displayed)
			'values'				=> array (),# Simple array of selectable values
			'title'					=> '',		# Introductory text
			'description'			=> '',		# Description text
			'get'					=> false,	# Whether a URL-supplied GET value should be used as the initial value (e.g. 'key' here would look in $_GET['key'] and supply that as the default)
			'append'				=> '',		# HTML appended to the widget
			'prepend'				=> '',		# HTML prepended to the widget
			'output'				=> array (),# Presentation format
			'multiple'				=> false,	# Whether to create a multiple-mode select box
			'expandable'			=> false,	# Whether a multiple-select box should be converted to a set of single boxes whose number can be incremented by pressing a + button
			'required'		=> 0,		# The minimum number which must be selected (defaults to 0)
			'size'			=> 5,		# Number of rows visible in multiple mode (optional; defaults to 1)
			'default'				=> array (),# Pre-selected item(s)
			'forceAssociative'		=> false,	# Force the supplied array of values to be associative
			'nullText'				=> $this->settings['nullText'],	# Override null text for a specific widget
			'discard'				=> false,	# Whether to process the input but then discard it in the results
			'datatype'				=> false,	# Datatype used for database writing emulation (or caching an actual value)
			'truncate'				=> $this->settings['truncate'],	# Override truncation setting for a specific widget
			'tabindex'				=> false,	# Tabindex if required; replace with integer between 0 and 32767 to create
		);
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, __FUNCTION__, NULL, $arrayType = true);
		
		$arguments = $widget->getArguments ();
		
		# If the values are not an associative array, convert the array to value=>value format and replace the initial array
		$arguments['values'] = $this->ensureHierarchyAssociative ($arguments['values'], $arguments['forceAssociative'], $arguments['name']);
		
		# If a multidimensional array, cache the multidimensional version, and flatten the main array values
		if (application::isMultidimensionalArray ($arguments['values'])) {
			$arguments['_valuesMultidimensional'] = $arguments['values'];
			$arguments['values'] = application::flattenMultidimensionalArray ($arguments['values']);
		}
		
		# If using a expandable widget-set, ensure that other arguments are sane
		$subwidgets = 1;
		if ($arguments['expandable']) {
			if (!$arguments['multiple']) {
				$this->formSetupErrors['expandableNotMultiple'] = 'An expandable select widget-set was requested, but the widget-type is not set as multiple, which is required.';
				$arguments['expandable'] = false;
			}
			if (!$arguments['editable']) {
				$this->formSetupErrors['expandableNotEditable'] = 'An expandable select widget-set was requested, but the widget-type is set as non-editable.';
				$arguments['expandable'] = false;
			}
			
			# Determine the number of widgets to display
			$subwidgets = 1;
			if ($arguments['required'] && is_numeric ($arguments['required'])) {
				$subwidgets = $arguments['required'];
			}
			$checkForSubwidgetsWidgetName = '__subwidgets_' . $this->cleanId ($arguments['name']);
			if (isSet ($this->collection[$checkForSubwidgetsWidgetName])) {
				if (ctype_digit ($this->collection[$checkForSubwidgetsWidgetName])) {
					$subwidgets = $this->collection[$checkForSubwidgetsWidgetName];
					$checkForRefreshWidgetName = '__refresh_' . $this->cleanId ($arguments['name']);
					if (isSet ($this->collection[$checkForRefreshWidgetName])) {
						$subwidgets++;
					}
				}
			}
			$totalAvailableOptions = count ($arguments['values']);
			if ($subwidgets > $totalAvailableOptions) {	// Ensure there are never any more than the available options
				$subwidgets = $totalAvailableOptions;
			}
		}
		
		# Use the 'get' supplied value if required
		#!# Apply this to checkboxes and radio buttons also
		if ($arguments['get']) {
			$arguments['default'] = application::urlSuppliedValue ($arguments['get'], array_keys ($arguments['values']));
		}
		
		# Ensure the initial value(s) is an array, even if only an empty one, converting if necessary
		$arguments['default'] = application::ensureArray ($arguments['default']);
		
		# Increase the number of default widgets to the number of defaults if any are set
		if ($arguments['expandable']) {
			if ($arguments['default']) {
				$totalDefaults = count ($arguments['default']);
				if ($totalDefaults > $subwidgets) {
					$subwidgets = $totalDefaults;
				}
			}
		}
		
		# If the widget is not editable, fix the form value to the default
		if (!$arguments['editable']) {$this->form[$arguments['name']] = $arguments['default'];}
		
		# Obtain the value of the form submission (which may be empty); if using expandable widgets, emulate the format of a single multiple widget
		if ($arguments['expandable']) {
			$value = array ();
			$allNonZeroSoFar = true;
			for ($subwidget = 0; $subwidget < $subwidgets; $subwidget++) {
				$subwidgetName = $arguments['name'] . ($arguments['expandable'] ? "_{$subwidget}" : '');
				if (isSet ($this->form[$subwidgetName]) && isSet ($this->form[$subwidgetName][0])) {
					$subwidgetValue = $this->form[$subwidgetName][0];
					if ($value && $subwidgetValue && in_array ($subwidgetValue, $value)) {
						$elementProblems['expandableValuesDuplicated'] = "In the <strong>{$arguments['name']}</strong> element, you selected the same value twice.";
					}
					if (!$subwidgetValue) {
						$allNonZeroSoFar = false;
					}
					$value[$subwidget] = $subwidgetValue;
				}
				if (!$allNonZeroSoFar && $subwidgetValue) {
					$elementProblems['expandableValuesMissingInSequence'] = "In the <strong>{$arguments['name']}</strong> element, you left out a value in sequence.";
				}
			}
		} else {
			$value = (isSet ($this->form[$arguments['name']]) ? $this->form[$arguments['name']] : array ());
		}
		$widget->setValue ($value);
		
		$elementValue = $widget->getValue ();
		
		# Check that the array of values is not empty
		if (empty ($arguments['values'])) {
			$this->formSetupErrors['selectNoValues'] = 'No values have been set as selection items.';
			return false;
		}
		
		# Apply truncation if necessary
		$arguments['values'] = $widget->truncate ($arguments['values']);
		
		# Check that the given minimum required is not more than the number of items actually available
		$totalSubItems = count ($arguments['values']);
		if ($arguments['required'] > $totalSubItems) {$this->formSetupErrors['selectMinimumMismatch'] = "The required minimum number of items which must be selected (<strong>{$arguments['required']}</strong>) specified is above the number of select items actually available (<strong>$totalSubItems</strong>).";}
		
		# If not using multiple mode, ensure that more than one cannot be set as required
		if (!$arguments['multiple'] && ($arguments['required'] > 1)) {$this->formSetupErrors['selectMultipleMismatch'] = "More than one value is set as being required to be selected but multiple mode is off. One or the other should be changed.";}
		
		# Ensure that there cannot be multiple initial values set if the multiple flag is off
		$totalDefaults = count ($arguments['default']);
		if ((!$arguments['multiple']) && ($totalDefaults > 1)) {
			$this->formSetupErrors['defaultTooMany'] = "In the <strong>{$arguments['name']}</strong> element, $totalDefaults total initial values were assigned but the form has been set up to allow only one item to be selected by the user.";
		}
		
		# Ensure that all initial values are in the array of values
		$this->ensureDefaultsAvailable ($arguments);
		
		# Emulate the need for the field to be 'required', i.e. the minimum number of fields is greater than 0
		$required = ($arguments['required'] > 0);
		
		# Loop through each element value to check that it is in the available values, and just discard without comment any that are not
		foreach ($elementValue as $index => $value) {
			if (!array_key_exists ($value, $arguments['values'])) {
				unset ($elementValue[$index]);
			}
		}
		
		# Remove null if it's submitted, so that it can never end up in the results; this is different to radiobuttons, because a radiobutton set can have nothing selected on first load, whereas a select always has something selected, so a null must be present
		foreach ($elementValue as $index => $submittedValue) {
			if ($submittedValue == '') {
				unset ($elementValue[$index]);
			}
		}
		
		# Produce a problem message if the number submitted is fewer than the number required
		$totalSubmitted = count ($elementValue);
		if (($totalSubmitted != 0) && ($totalSubmitted < $arguments['required'])) {
			$elementProblems['insufficientSelected'] = ($arguments['required'] != $totalSubItems ? 'At least' : 'All') . " <strong>{$arguments['required']}</strong> " . ($arguments['required'] > 1 ? 'items' : 'item') . ' must be selected.';
		}
		
		# Prevent multiple submissions when not in multiple mode
		if (!$arguments['multiple'] && ($totalSubmitted > 1)) {$elementProblems['multipleSubmissionsDisallowed'] = 'More than one item was submitted but only one is acceptable';}
		
		# If nothing has been submitted mark it as required but empty
		$requiredButEmpty = (($required) && ($totalSubmitted == 0));
		
		# Assign the initial values if the form is not posted (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid)
		if (!$this->formPosted) {$elementValue = $arguments['default'];}
		
		# Describe restrictions on the widget
		if ($arguments['multiple']) {
			$restriction = (($arguments['required'] > 1) ? "Minimum {$arguments['required']} required." : '') . ($arguments['expandable'] ? '' : ' Use Control/Shift for multiple');
		}
		
		# Define the widget's core HTML
		if ($arguments['editable']) {
			
			# Create each widget for this set (normally one, but could be more if in expandable mode)
			$subwidgetHtml = array ();
			$subwidgetsAreMultiple = ($arguments['expandable'] ? false : $arguments['multiple']);
			for ($subwidget = 0; $subwidget < $subwidgets; $subwidget++) {
				$subwidgetName = $arguments['name'] . ($arguments['expandable'] ? "_{$subwidget}" : '');
				
				# Add a null field to the selection if in multiple mode and a value is required (for single fields, null is helpful; for multiple not required, some users may not know how to de-select a field)
				#!# Creates error if formSetupErrors['selectNoValues'] thrown - shouldn't be getting this far
				if (!$subwidgetsAreMultiple || !$arguments['required']) {
					$arguments['valuesWithNull'] = array ('' => $arguments['nullText']) + $arguments['values'];
					if (isSet ($arguments['_valuesMultidimensional'])) {
						$arguments['_valuesMultidimensional'] = array ('' => $arguments['nullText']) + $arguments['_valuesMultidimensional'];
					}
				}
				
				# Create the widget; this has to split between a non- and a multi-dimensional array because converting all to the latter makes it indistinguishable from a single optgroup array
				$subwidgetHtml[$subwidget] = "\n\t\t\t<select" . $this->nameIdHtml ($subwidgetName, true) . ($subwidgetsAreMultiple ? " multiple=\"multiple\" size=\"{$arguments['size']}\"" : '') . $widget->tabindexHtml () . '>';
				if (!isSet ($arguments['_valuesMultidimensional'])) {
					$arguments['valuesWithNull'] = array ('' => $arguments['nullText']) + $arguments['values'];
					foreach ($arguments['valuesWithNull'] as $availableValue => $visible) {
						$isSelected = $this->select_isSelected ($arguments['expandable'], $elementValue, $subwidget, $availableValue);
						$subwidgetHtml[$subwidget] .= "\n\t\t\t\t" . '<option value="' . htmlspecialchars ($availableValue) . '"' . ($isSelected ? ' selected="selected"' : '') . $this->nameIdHtml ($subwidgetName, false, $availableValue, true, $idOnly = true) . '>' . htmlspecialchars ($visible) . '</option>';
					}
				} else {
					
					# Multidimensional version, which adds optgroup labels
					foreach ($arguments['_valuesMultidimensional'] as $key => $mainValue) {
						if (is_array ($mainValue)) {
							$subwidgetHtml[$subwidget] .= "\n\t\t\t\t\t<optgroup label=\"{$key}\">";
							foreach ($mainValue as $availableValue => $visible) {
								$isSelected = $this->select_isSelected ($arguments['expandable'], $elementValue, $subwidget, $availableValue);
								$subwidgetHtml[$subwidget] .= "\n\t\t\t\t\t\t" . '<option value="' . htmlspecialchars ($availableValue) . '"' . ($isSelected ? ' selected="selected"' : '') . '>' . htmlspecialchars ($visible) . '</option>';
							}
							$subwidgetHtml[$subwidget] .= "\n\t\t\t\t\t</optgroup>";
						} else {
							$isSelected = $this->select_isSelected ($arguments['expandable'], $elementValue, $subwidget, $key);
							$subwidgetHtml[$subwidget] .= "\n\t\t\t\t" . '<option value="' . htmlspecialchars ($key) . '"' . ($isSelected ? ' selected="selected"' : '') . '>' . htmlspecialchars ($mainValue) . '</option>';
						}
					}
				}
				$subwidgetHtml[$subwidget] .= "\n\t\t\t</select>\n\t\t";
			}
			
			# Add an expansion button at the end
			if ($arguments['expandable']) {
				#!# Need to deny __refresh_<cleaned-id> and __subwidgets_<cleaned-id> as a reserved form name
				$refreshButton  = '<input type="hidden" value="' . $subwidgets . '" name="__subwidgets_' . $this->cleanId ($arguments['name']) . '" />';
				$refreshButton .= '<input type="submit" value="&#10010;" title="Add another item" name="__refresh_' . $this->cleanId ($arguments['name']) . '" class="refresh" />';
				$arguments['append'] = $refreshButton . $arguments['append'];
			}
			
			# Compile the subwidgets into a single widget HTML block
			$widgetHtml  = implode ("\t<br />", $subwidgetHtml);
			
		} else {	// i.e. Non-editable
			
			# Loop through each default argument (if any) to prepare them
			#!# All this stuff isn't even needed if errors have been found
			#!# Need to double-check that $arguments['default'] isn't being changed above this point [$arguments['default'] is deliberately used here because of the $identifier system above]
			$presentableDefaults = array ();
			foreach ($arguments['default'] as $argument) {
				if (isSet ($arguments['values'][$argument])) {
					$presentableDefaults[$argument] = $arguments['values'][$argument];
				}
			}
			
			# Set the widget HTML
			$widgetHtml  = implode ("<span class=\"comment\">,</span>\n<br />", array_values ($presentableDefaults));
			if (!$presentableDefaults) {
				$widgetHtml .= "\n\t\t\t<span class=\"comment\">(None)</span>";
			} else {
				foreach ($presentableDefaults as $value => $visible) {
					$widgetHtml .= "\n\t\t\t" . '<input' . $this->nameIdHtml ($arguments['name'], true /* True should be used so that the _POST is the same structure (which is useful if the user is capturing that data before using its API), even though this is actually ignored in processing */) . ' type="hidden" value="' . htmlspecialchars ($value) . '" />';
				}
			}
			
			# Re-assign the values back to the 'submitted' form value
			$elementValue = array_keys ($presentableDefaults);
		}
		
		# Re-assign back the value
		$this->form[$arguments['name']] = $elementValue;
		
		# Get the posted data; there is no need to clear null or fake submissions because it will just get ignored, not being in the values list
		if ($this->formPosted) {
			
			# Loop through each defined element name
			$chosenValues = array ();
			$chosenVisible = array ();
			foreach ($arguments['values'] as $value => $visible) {
				
				# Determine if the value has been submitted
				$isSubmitted = (in_array ($value, $this->form[$arguments['name']]));
				
				# rawcomponents is 'An array with every defined element being assigned as itemName => boolean true/false'
				$data['rawcomponents'][$value] = $isSubmitted;
				
				# compiled is 'String of checked items only as selectedItemName1\n,selectedItemName2\n,selectedItemName3'
				# presented is 'As compiled, but in the case of an associative array of values being supplied as selectable items, the visible text version used instead of the actual value'
				if ($isSubmitted) {
					$chosenValues[] = $value;
					$chosenVisible[] = $visible;
				}
			}
			
			# Assemble the compiled and presented versions
			$data['compiled'] = implode (",\n", $chosenValues);
			$data['presented'] = implode (",\n", $chosenVisible);
		}
		
		# Compile the datatype
		foreach ($arguments['values'] as $key => $value) {
			$datatype[] = str_replace ("'", "\'", $key);
		}
		
		# Add the widget to the master array for eventual processing
		$this->elements[$arguments['name']] = array (
			'type' => __FUNCTION__,
			'html' => $arguments['prepend'] . $widgetHtml . $arguments['append'],
			'title' => $arguments['title'],
			'description' => $arguments['description'],
			'restriction' => (isSet ($restriction) && $arguments['editable'] ? $restriction : false),
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $required,
			'requiredButEmpty' => $requiredButEmpty,
			'suitableAsEmailTarget' => $this->_suitableAsEmailTarget (array_keys ($arguments['values']), $arguments),
			'output' => $arguments['output'],
			'discard' => $arguments['discard'],
			'data' => (isSet ($data) ? $data : NULL),
			'values' => $arguments['values'],
			'multiple' => $arguments['multiple'],
			'datatype' => ($arguments['datatype'] ? $arguments['datatype'] : "`{$arguments['name']}` " . "ENUM ('" . implode ("', '", $datatype) . "')") . ($arguments['required'] ? ' NOT NULL' : '') . " COMMENT '" . (addslashes ($arguments['title'])) . "'",
			'groupValidation' => 'compiled',
		);
	}
	
	
	# Helper function for select fields to determe whether a value is selected
	function select_isSelected ($expandable, $elementValue, $subwidget, $availableValue)
	{
		if ($expandable) {
			$isSelected = (isSet ($elementValue[$subwidget]) ? ($availableValue == $elementValue[$subwidget]) : false);
		} else {
			$isSelected = (in_array ($availableValue, $elementValue));
		}
		return $isSelected;
	}
	
	
	/**
	 * Create a radio-button widget set
	 * @param array $arguments Supplied arguments - see template
	 */
	function radiobuttons ($suppliedArguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentDefaults = array (
			'name'					=> NULL,	# Name of the element
			'editable'				=> true,	# Whether the widget is editable (if not, a hidden element will be substituted but the value displayed)
			'values'				=> array (),# Simple array of selectable values
			'title'					=> '',		# Introductory text
			'description'			=> '',		# Description text
			'append'				=> '',		# HTML appended to the widget
			'prepend'				=> '',		# HTML prepended to the widget
			'output'				=> array (),# Presentation format
			'required'				=> false,	# Whether required or not
			'default'				=> array (),# Pre-selected item
			'linebreaks'			=> $this->settings['linebreaks'],	# Whether to put line-breaks after each widget: true = yes (default) / false = none / array (1,2,5) = line breaks after the 1st, 2nd, 5th items
			'forceAssociative'		=> false,	# Force the supplied array of values to be associative
			'nullText'				=> $this->settings['nullText'],	# Override null text for a specific widget (if false, the master value is assumed)
			'discard'				=> false,	# Whether to process the input but then discard it in the results
			'datatype'				=> false,	# Datatype used for database writing emulation (or caching an actual value)
			'truncate'				=> $this->settings['truncate'],	# Override truncation setting for a specific widget
			'tabindex'				=> false,	# Tabindex if required; replace with integer between 0 and 32767 to create
			'entities'				=> true,	# Convert HTML in label to entity equivalents
		);
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, __FUNCTION__, NULL, $arrayType = false);
		
		$arguments = $widget->getArguments ();
		
		# If the widget is not editable, fix the form value to the default
		if (!$arguments['editable']) {$this->form[$arguments['name']] = $arguments['default'];}
		
		# Do a sanity-check to check that a non-editable field can succeed
		#!# Apply to all cases?
		if (!$arguments['editable'] && $arguments['required'] && !$arguments['default']) {
			$this->formSetupErrors['defaultTooMany'] = "In the <strong>{$arguments['name']}</strong> element, you cannot set a non-editable field to be required but have no initial value.";
		}
		
		# Obtain the value of the form submission (which may be empty)
		$widget->setValue (isSet ($this->form[$arguments['name']]) ? $this->form[$arguments['name']] : '');
		
		$elementValue = $widget->getValue ();
		
		# Check that the array of values is not empty
		if (empty ($arguments['values'])) {
			$this->formSetupErrors['radiobuttonsNoValues'] = 'No values have been set as selection items.';
			return false;
		}
		
		# If the values are not an associative array, convert the array to value=>value format and replace the initial array
		$arguments['values'] = $this->ensureHierarchyAssociative ($arguments['values'], $arguments['forceAssociative'], $arguments['name']);
		
		# Apply truncation if necessary
		$arguments['values'] = $widget->truncate ($arguments['values']);
		
		/* #!# Enable when implementing fieldset grouping
		# If a multidimensional array, cache the multidimensional version, and flatten the main array values
		if (application::isMultidimensionalArray ($arguments['values'])) {
			$arguments['_valuesMultidimensional'] = $arguments['values'];
			$arguments['values'] = application::flattenMultidimensionalArray ($arguments['values']);
		}
		*/
		
		# Loop through each element value to check that it is in the available values, and just discard without comment any that are not
		if (!array_key_exists ($elementValue, $arguments['values'])) {
			$elementValue = false;
		}
		
		# Check whether the field satisfies any requirement for a field to be required
		#!# Migrate this to using $widget->requiredButEmpty when $widget->setValue uses references not copied values
		$requiredButEmpty = ($arguments['required'] && (strlen ($elementValue) == 0));
		
		# Ensure that there cannot be multiple initial values set if the multiple flag is off; note that the default can be specified as an array, for easy swapping with a select (which in singular mode behaves similarly)
		$arguments['default'] = application::ensureArray ($arguments['default']);
		if (count ($arguments['default']) > 1) {
			$this->formSetupErrors['defaultTooMany'] = "In the <strong>{$arguments['name']}</strong> element, $totalDefaults total initial values were assigned but only one can be set as a default.";
		}
		
		# Ensure that all initial values are in the array of values
		$this->ensureDefaultsAvailable ($arguments);
		
		# If the field is not a required field (and therefore there is a null text field), ensure that none of the values have an empty string as the value (which is reserved for the null)
		#!# Policy question: should empty values be allowed at all? If so, make a special constant for a null field but which doesn't have the software name included
		if (!$arguments['required'] && in_array ('', array_keys ($arguments['values']), true)) {
			$this->formSetupErrors['defaultNullClash'] = "In the <strong>{$arguments['name']}</strong> element, one value was assigned to an empty value (i.e. '').";
		}
		
		# Assign the initial value if the form is not posted (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid)
		if (!$this->formPosted) {
			foreach ($arguments['default'] as $elementValue) {}
		}
		
		# Define the widget's core HTML
		$widgetHtml = '';
		if ($arguments['editable']) {
			$subwidgetIndex = 1;
			
			# If it's not a required field, add a null field to the selection
			if (!$arguments['required']) {
				#!# Does the 'withNull' fix made to version 1.0.2 need to be applied here?
				$arguments['values'] = array ('' => $arguments['nullText']) + $arguments['values'];
				/* #!# Enable when implementing fieldset grouping
				if (isSet ($arguments['_valuesMultidimensional'])) {
					$arguments['_valuesMultidimensional'] = array ('' => $arguments['nullText']) + $arguments['_valuesMultidimensional'];
				}
				*/
			}
			
			# Create the widget
			/* #!# Write branching code around here which uses _valuesMultidimensional, when implementing fieldset grouping */
			foreach ($arguments['values'] as $value => $visible) {
				$elementId = $this->cleanId ($this->settings['name'] ? "{$this->settings['name']}[{$arguments['name']}_{$value}]" : "{$arguments['name']}_{$value}");
				
				#!# Dagger hacked in - fix properly for other such characters; consider a flag somewhere to allow entities and HTML tags to be incorporated into the text (but then cleaned afterwards when printed/e-mailed)
				$widgetHtml .= "\n\t\t\t" . '<input type="radio"' . $this->nameIdHtml ($arguments['name'], false, $value) . ' value="' . htmlspecialchars ($value) . '"' . ($value == $elementValue ? ' checked="checked"' : '') . $widget->tabindexHtml ($subwidgetIndex - 1) . " /><label for=\"" . $elementId . '">' . ($arguments['entities'] ? htmlspecialchars ($visible) : $visible) . '</label>';
				
				# Add a line break if required
				if (($arguments['linebreaks'] === true) || (is_array ($arguments['linebreaks']) && in_array ($subwidgetIndex, $arguments['linebreaks']))) {$widgetHtml .= '<br />';}
				$subwidgetIndex++;
			}
			$widgetHtml .= "\n\t\t";
		} else {
			
			# Set the widget HTML if any default is given
			if ($arguments['default']) {
				foreach ($arguments['values'] as $value => $visible) {
					if ($value == $elementValue) {	// This loop is done to prevent offsets which may still arise due to the 'defaultMissingFromValuesArray' error not resulting in further termination of widget production
						#!# Offset generated here if editable false and the preset value not present
						$widgetHtml  = htmlspecialchars ($arguments['values'][$elementValue]);
						$widgetHtml .= "\n\t\t\t" . '<input' . $this->nameIdHtml ($arguments['name'], false, $elementValue) . ' type="hidden" value="' . htmlspecialchars ($elementValue) . '" />';
					}
				}
			}
		}
		
		# Re-assign back the value
		$this->form[$arguments['name']] = $elementValue;
		
		# Get the posted data; there is no need to clear null submissions because it will just get ignored, not being in the values list
		if ($this->formPosted) {
			
			# For the rawcomponents version, create An array with every defined element being assigned as itemName => boolean true/false
			$data['rawcomponents'] = array ();
			foreach ($arguments['values'] as $value => $visible) {
				$data['rawcomponents'][$value] = ($this->form[$arguments['name']] == $value);
			}
			
			# Take the selected option and ensure that this is in the array of available values
			#!# What if it's not? - This check should be moved up higher
			$data['compiled'] = (in_array ($this->form[$arguments['name']], array_keys ($arguments['values'])) ? $this->form[$arguments['name']] : '');
			
			# For the presented version, use the visible text version
			$data['presented'] = (in_array ($this->form[$arguments['name']], array_keys ($arguments['values'])) ? $arguments['values'][$this->form[$arguments['name']]] : '');
		}
		
		# Compile the datatype
		foreach ($arguments['values'] as $key => $value) {
			$datatype[] = str_replace ("'", "\'", $key);
		}
		
		# Add the widget to the master array for eventual processing
		$this->elements[$arguments['name']] = array (
			'type' => __FUNCTION__,
			'html' => $arguments['prepend'] . $widgetHtml . $arguments['append'],
			'title' => $arguments['title'],
			'description' => $arguments['description'],
			'restriction' => (isSet ($restriction) && $arguments['editable'] ? $restriction : false),
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $arguments['required'],
			'requiredButEmpty' => $requiredButEmpty,
			'suitableAsEmailTarget' => $arguments['required'],
			'output' => $arguments['output'],
			'discard' => $arguments['discard'],
			'data' => (isSet ($data) ? $data : NULL),
			'values' => $arguments['values'],
			'datatype' => ($arguments['datatype'] ? $arguments['datatype'] : "`{$arguments['name']}` " . "ENUM ('" . implode ("', '", $datatype) . "')") . ($arguments['required'] ? ' NOT NULL' : '') . " COMMENT '" . (addslashes ($arguments['title'])) . "'",
			'groupValidation' => 'compiled',
		);
	}
	
	
	/**
	 * Create a checkbox(es) widget set
	 * @param array $arguments Supplied arguments - see template
	 */
	function checkboxes ($suppliedArguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentDefaults = array (
			'name'					=> NULL,	# Name of the element
			'editable'				=> true,	# Whether the widget is editable (if not, a hidden element will be substituted but the value displayed)
			#!# Missing this value out causes errors lower
			'values'				=> array (),# Simple array of selectable values
			'title'					=> '',		# Introductory text
			'description'			=> '',		# Description text
			'append'				=> '',		# HTML appended to the widget
			'prepend'				=> '',		# HTML prepended to the widget
			'output'				=> array (),# Presentation format
			'required'		=> 0,		# The minimum number which must be selected (defaults to 0)
			'maximum'		=> 0,		# The maximum number which must be selected (defaults to 0, i.e. no maximum checking done)
			'default'			=> array (),# Pre-selected item(s)
			'forceAssociative'		=> false,	# Force the supplied array of values to be associative
			'linebreaks'			=> $this->settings['linebreaks'],	# Whether to put line-breaks after each widget: true = yes (default) / false = none / array (1,2,5) = line breaks after the 1st, 2nd, 5th items
			'columns'				=> false,	# Split into columns
			'discard'				=> false,	# Whether to process the input but then discard it in the results
			'datatype'				=> false,	# Datatype used for database writing emulation (or caching an actual value)
			'truncate'				=> $this->settings['truncate'],	# Override truncation setting for a specific widget
			'tabindex'				=> false,	# Tabindex if required; replace with integer between 0 and 32767 to create
			'entities'				=> true,	# Convert HTML in label to entity equivalents
		);
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, __FUNCTION__, NULL, $arrayType = true);
		
		$arguments = $widget->getArguments ();
		
		# Ensure the initial value(s) is an array, even if only an empty one, converting if necessary
		$arguments['default'] = application::ensureArray ($arguments['default']);
		
		# Obtain the value of the form submission (which may be empty)
		$widget->setValue (isSet ($this->form[$arguments['name']]) ? $this->form[$arguments['name']] : array ());
		
		$elementValue = $widget->getValue ();
		
		# If the values are not an associative array, convert the array to value=>value format and replace the initial array
		$arguments['values'] = $this->ensureHierarchyAssociative ($arguments['values'], $arguments['forceAssociative'], $arguments['name']);
		
		# Check that the array of values is not empty
		if (empty ($arguments['values'])) {
			$this->formSetupErrors['checkboxesNoValues'] = 'No values have been set for the set of checkboxes.';
			return false;
		}
		
		# Apply truncation if necessary
		$arguments['values'] = $widget->truncate ($arguments['values']);
		
		/* #!# Enable when implementing fieldset grouping
		# If a multidimensional array, cache the multidimensional version, and flatten the main array values
		if (application::isMultidimensionalArray ($arguments['values'])) {
			$arguments['_valuesMultidimensional'] = $arguments['values'];
			$arguments['values'] = application::flattenMultidimensionalArray ($arguments['values']);
		}
		*/
		
		# Check that the given minimum required is not more than the number of checkboxes actually available
		$totalSubItems = count ($arguments['values']);
		if ($arguments['required'] > $totalSubItems) {$this->formSetupErrors['checkboxesMinimumMismatch'] = "In the <strong>{$arguments['name']}</strong> element, The required minimum number of checkboxes (<strong>{$arguments['required']}</strong>) specified is above the number of checkboxes actually available (<strong>$totalSubItems</strong>).";}
		if ($arguments['maximum'] && $arguments['required'] && ($arguments['maximum'] < $arguments['required'])) {$this->formSetupErrors['checkboxesMaximumMismatch'] = "In the <strong>{$arguments['name']}</strong> element, A maximum and a minimum number of checkboxes have both been specified but this maximum (<strong>{$arguments['maximum']}</strong>) is less than the minimum (<strong>{$arguments['required']}</strong>) required.";}
		
		# Ensure that all initial values are in the array of values
		$this->ensureDefaultsAvailable ($arguments);
		
		# Start a tally to check the number of checkboxes checked
		$checkedTally = 0;
		
		# Determine whether to use columns, and ensure there are no more than the number of arguments, then set the number per column
		if ($splitIntoColumns = ($arguments['columns'] && ctype_digit ((string) $arguments['columns']) && ($arguments['columns'] > 1) ? min ($arguments['columns'], count ($arguments['values'])) : false)) {
			$splitIntoColumns = ceil (count ($arguments['values']) / $splitIntoColumns);
		}
		
		# Loop through each pre-defined element subname to construct the HTML
		$widgetHtml = '';
		if ($arguments['editable']) {
			/* #!# Write branching code around here which uses _valuesMultidimensional, when implementing fieldset grouping */
			$subwidgetIndex = 1;
			if ($splitIntoColumns) {$widgetHtml .= "\n\t\t\t<table class=\"checkboxcolumns\">\n\t\t\t\t<tr>\n\t\t\t\t\t<td>";}
			foreach ($arguments['values'] as $value => $visible) {
				
				# If the form is not posted, assign the initial value (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid)
				if (!$this->formPosted) {
					if (in_array ($value, $arguments['default'])) {
						$elementValue[$value] = true;
					}
				}
				
				# Apply stickyness to each checkbox if necessary
				$stickynessHtml = '';
				if (isSet ($elementValue[$value])) {
					if ($elementValue[$value]) {
						$stickynessHtml = ' checked="checked"';
						
						# Tally the number of items checked
						$checkedTally++;
					}
				} else {
					# Ensure every element is defined (even if empty), so that the case of writing to a file doesn't go wrong
					$elementValue[$value] = '';
				}
				
//				# Construct the element ID, which must be unique	
				$elementId = $this->cleanId ($this->settings['name'] ? "{$this->settings['name']}[{$arguments['name']}_{$value}]" : "{$arguments['name']}_{$value}");
				
				# Create the HTML; note that spaces (used to enable the 'label' attribute for accessibility reasons) in the ID will be replaced by an underscore (in order to remain valid XHTML)
//				//$widgetHtml .= "\n\t\t\t" . '<input type="checkbox" name="' . ($this->settings['name'] ? "{$this->settings['name']}[{$arguments['name']}]" : $arguments['name']) . "[{$value}]" . '" id="' . $elementId . '" value="true"' . $stickynessHtml . ' /><label for="' . $elementId . '">' . htmlspecialchars ($visible) . '</label>';
				$widgetHtml .= "\n\t\t\t\t" . ($splitIntoColumns ? "\t\t" : '') . '<input type="checkbox"' . $this->nameIdHtml ($arguments['name'], false, $value, true) . ' value="true"' . $stickynessHtml . $widget->tabindexHtml ($subwidgetIndex - 1) . ' /><label for="' . $elementId . '">' . ($arguments['entities'] ? htmlspecialchars ($visible) : $visible) . '</label>';
				
				# Add a line/column breaks when required
				if (($arguments['linebreaks'] === true) || (is_array ($arguments['linebreaks']) && in_array ($subwidgetIndex, $arguments['linebreaks']))) {$widgetHtml .= '<br />';}
				if ($splitIntoColumns) {
					if (($subwidgetIndex % $splitIntoColumns) == 0) {
						if ($subwidgetIndex != count ($arguments['values'])) { // Don't add at the end if the number is an exact multiplier
							$widgetHtml .= "\n\t\t\t\t\t</td>\n\t\t\t\t\t<td>";
						}
					}
				}
				$subwidgetIndex++;
			}
			if ($splitIntoColumns) {$widgetHtml .= "\n\t\t\t\t\t</td>\n\t\t\t\t</tr>\n\t\t\t</table>\n\t\t";}
		} else {
			
			# Loop through each default argument (if any) to prepare them
			#!# Need to double-check that $arguments['default'] isn't being changed above this point [$arguments['default'] is deliberately used here because of the $identifier system above]
			$presentableDefaults = array ();
			foreach ($arguments['default'] as $argument) {
				$presentableDefaults[$argument] = $arguments['values'][$argument];
			}
			
			# Set the widget HTML
			$widgetHtml  = implode ("<span class=\"comment\">,</span>\n<br />", array_values ($presentableDefaults));
			if (!$presentableDefaults) {
				$widgetHtml .= "\n\t\t\t<span class=\"comment\">(None)</span>";
			} else {
				foreach ($presentableDefaults as $value => $visible) {
					$widgetHtml .= "\n\t\t\t" . '<input' . $this->nameIdHtml ($arguments['name'], false, $value, true) . ' type="hidden" value="true" />';
				}
			}
			
			# Re-assign the values back to the 'submitted' form value
			$elementValue = array ();
			foreach ($arguments['default'] as $argument) {
				$elementValue[$argument] = 'true';
			}
		}
		
		# Make sure the number of checkboxes given is above the $arguments['required']
		if ($checkedTally < $arguments['required']) {
			$elementProblems['insufficientSelected'] = "A minimum of {$arguments['required']} " . ($arguments['required'] == 1 ? 'item' : 'items') . ' must be selected';
		}
		
		# Make sure the number of checkboxes given is above the maximum required
		if ($arguments['maximum']) {
			if ($checkedTally > $arguments['maximum']) {
				$elementProblems['tooManySelected'] = "A maximum of {$arguments['maximum']} " . ($arguments['maximum'] == 1 ? 'item' : 'items') . ' can be selected';
			}
		}
		
		# Describe restrictions on the widget
		#!# Rewrite a more complex but clearer description, e.g. "exactly 3", "between 1 and 3 must", "at least 1", "between 0 and 3 can", etc
		if ($arguments['required']) {$restriction[] = "A minimum of {$arguments['required']} " . ($arguments['required'] == 1 ? 'item' : 'items') . ' must be selected';}
		if ($arguments['maximum']) {$restriction[] = "A maximum of {$arguments['maximum']} " . ($arguments['maximum'] == 1 ? 'item' : 'items') . ' can be selected';}
		if (isSet ($restriction)) {
			$restriction = implode (';<br />', $restriction);
		}
		
		# Re-assign back the value
		$this->form[$arguments['name']] = $elementValue;
		
		# Get the posted data; there is no need to clear null or fake submissions because it will just get ignored, not being in the values list
		if ($this->formPosted) {
			
			# Loop through each defined element name
			$chosenValues = array ();
			$chosenVisible = array ();
			foreach ($arguments['values'] as $value => $visible) {
				
				# Determine if the value has been submitted
				$isSubmitted = (isSet ($this->form[$arguments['name']][$value]) && $this->form[$arguments['name']][$value] == 'true');
				
				# rawcomponents is 'An array with every defined element being assigned as itemName => boolean true/false'
				$data['rawcomponents'][$value] = $isSubmitted;
				
				# compiled is 'String of checked items only as selectedItemName1\n,selectedItemName2\n,selectedItemName3'
				# presented is 'As compiled, but in the case of an associative array of values being supplied as selectable items, the visible text version used instead of the actual value'
				if ($isSubmitted) {
					$chosenValues[] = $value;
					$chosenVisible[] = $visible;
				}
			}
			
			# Assemble the compiled and presented versions
			$data['compiled'] = implode (",\n", $chosenValues);
			$data['presented'] = implode (",\n", $chosenVisible);
			$data['special-setdatatype'] = implode (',', $chosenValues);
		}
		
		# Compile the datatype
		$checkboxDatatypes = array ();
		foreach ($arguments['values'] as $key => $value) {
			#!# NOT NULL handling needs to be inserted
			$checkboxDatatypes[] = "`" . /* $arguments['name'] . '-' . */ str_replace ("'", "\'", $key) . "` " . "ENUM ('true', 'false')" . " COMMENT '" . (addslashes ($arguments['title'])) . "'";
		}
		$datatype = implode (",\n", $checkboxDatatypes);
		
		# Add the widget to the master array for eventual processing
		$this->elements[$arguments['name']] = array (
			'type' => __FUNCTION__,
			'html' => $arguments['prepend'] . $widgetHtml . $arguments['append'],
			'title' => $arguments['title'],
			'description' => $arguments['description'],
			'restriction' => (isSet ($restriction) && $arguments['editable'] ? $restriction : false),
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $arguments['required'],
			'requiredButEmpty' => false, # This is covered by $elementProblems
			#!# Apply $this->_suitableAsEmailTarget () to checkboxes possibly
			'suitableAsEmailTarget' => false,
			'output' => $arguments['output'],
			'discard' => $arguments['discard'],
			'data' => (isSet ($data) ? $data : NULL),
			'values' => $arguments['values'],
			#!# Not correct - needs multisplit into boolean
			'datatype' => ($arguments['datatype'] ? $arguments['datatype'] : $datatype),
			'groupValidation' => 'compiled',
			'total' => $checkedTally,
		);
	}
	
	
	/**
	 * Create a date/datetime widget set
	 * @param array $arguments Supplied arguments - see template
	 */
	function datetime ($suppliedArguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentDefaults = array (
			'name'					=> NULL,	# Name of the element
			'editable'				=> true,	# Whether the widget is editable (if not, a hidden element will be substituted but the value displayed)
			'title'					=> '',		# Introductory text
			'description'			=> '',		# Description text
			'append'				=> '',		# HTML appended to the widget
			'prepend'				=> '',		# HTML prepended to the widget
			'output'				=> array (),# Presentation format
			'required'				=> false,	# Whether required or not
			'level'					=> 'date',	# Whether to show 'datetime' / 'date' / 'time' / 'year' widget set
			'default'				=> '',		# Initial value - either 'timestamp' or an SQL string
			'discard'				=> false,	# Whether to process the input but then discard it in the results
			'datatype'				=> false,	# Datatype used for database writing emulation (or caching an actual value)
			'autoCenturyConversion'	=> 69,		# The last two figures of the last year where '20' is automatically prepended, or false to disable (and thus require four-digit entry)
			'tabindex'				=> false,	# Tabindex if required; replace with integer between 0 and 32767 to create
		);
		
		# Define the supported levels
		$levels = array (
			'time'		=> 'H:i:s',			// Widget order: t
			'datetime'	=> 'Y-m-d H:i:s',	// Widget order: tdmy
			'date'		=> 'Y-m-d',			// Widget order: dmy
			'year'		=> 'Y',				// Widget order: y
		);
		
		# Load the date processing library
		#!# Ideally this really should be higher up in the class, e.g. in the setup area
		require_once ('timedate.php');
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, __FUNCTION__);
		
		$arguments = $widget->getArguments ();
		
		# Check the level is supported
		if (!array_key_exists ($arguments['level'], $levels)) {
			$this->formSetupErrors['levelInvalid'] = "An invalid 'level' (" . htmlspecialchars ($arguments['level']) . ') was specified in the ' . htmlspecialchars ($arguments['name']) . ' datetime widget.';
			#!# Really this should end at this point rather than adding a fake reassignment
			$arguments['level'] = 'datetime';
		}
		
		# Convert the default if using the 'timestamp' keyword; cache a copy for later use; add a null date for the time version
		$isTimestamp = ($arguments['default'] == 'timestamp');
		if ($isTimestamp) {
			$arguments['default'] = date ($levels[$arguments['level']]);
		}
		
		# If the widget is not editable, fix the form value to the default
		if (!$arguments['editable']) {$this->form[$arguments['name']] = timedate::getDateTimeArray ((($arguments['level'] == 'time') ? '0000-00-00 ' : '') . $arguments['default']);}
		
		# Obtain the value of the form submission (which may be empty)  (ensure that a full date and time array exists to prevent undefined offsets in case an incomplete set has been posted)
		$value = (isSet ($this->form[$arguments['name']]) ? $this->form[$arguments['name']] : array ());
		$fields = array ('time', 'day', 'month', 'year', );
		foreach ($fields as $field) {
			if (!isSet ($value[$field])) {
				$value[$field] = '';
			}
		}
		$widget->setValue ($value);
		
		$elementValue = $widget->getValue ();
		
		# Start a flag later used for checking whether all fields are empty against the requirement that a field should be completed
		$requiredButEmpty = false;
		
		# Assign the initial value if the form is not posted (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid)
		if (!$this->formPosted) {
			$elementValue = timedate::getDateTimeArray ((($arguments['level'] == 'time') ? '0000-00-00 ' : '') . $arguments['default']);
		} else {
 			
			# Ensure all numeric fields are numeric, and reset to an empty string if so
			$fields = array ('day', 'month', 'year', );
			foreach ($fields as $field) {
				if (isSet ($elementValue[$field]) && !empty ($elementValue[$field])) {
					$elementValue[$field] = trim ($elementValue[$field]);
					if (!ctype_digit ($elementValue[$field])) {
						$elementValue[$field] = '';
					}
				}
			}
			
			# Check whether all fields are empty, starting with assuming all fields are not incomplete
			#!# This section needs serious (switch-based?) refactoring
			#!# Check about empty(0)
			$allFieldsIncomplete = false;
			if ($arguments['level'] == 'datetime') {
				if ((empty ($elementValue['day'])) && (empty ($elementValue['month'])) && (empty ($elementValue['year'])) && (empty ($elementValue['time']))) {$allFieldsIncomplete = true;}
			} else if ($arguments['level'] == 'year') {
				if (empty ($elementValue['year'])) {$allFieldsIncomplete = true;}
				# Emulate the day and month as being the first, to avoid branching the logic
				$elementValue['day'] = 1;
				$elementValue['month'] = 1;
			} else if ($arguments['level'] == 'time') {
				if (empty ($elementValue['time'])) {$allFieldsIncomplete = true;}
			} else {
				if ((empty ($elementValue['day'])) && (empty ($elementValue['month'])) && (empty ($elementValue['year']))) {$allFieldsIncomplete = true;}
			}
			
			# If all fields are empty, and the widget is required, set that the field is required but empty
			if ($allFieldsIncomplete) {
				if ($arguments['required']) {$requiredButEmpty = true;}
			} else {
				
				# Do date-based checks
				if ($arguments['level'] != 'time') {
					
					# If automatic conversion is set and the year is two characters long, convert the date to four years by adding 19 or 20 as appropriate
					if (($arguments['autoCenturyConversion']) && (strlen ($elementValue['year']) == 2)) {
						$elementValue['year'] = (($elementValue['year'] <= $arguments['autoCenturyConversion']) ? '20' : '19') . $elementValue['year'];
					}
					
					# Deal with month conversion by adding leading zeros as required
					if (($elementValue['month'] > 0) && ($elementValue['month'] <= 12)) {$elementValue['month'] = sprintf ('%02s', $elementValue['month']);}
					
					# Check that all parts have been completed
					if ((empty ($elementValue['day'])) || (empty ($elementValue['month'])) || (empty ($elementValue['year'])) || (($arguments['level'] == 'datetime') && (empty ($elementValue['time'])))) {
						$elementProblems['notAllComplete'] = "Not all parts have been completed!";
					} else {
						
						# Check that a valid month (01-12, corresponding to Jan-Dec respectively) has been submitted
						if ($elementValue['month'] > 12) {
							$elementProblems['monthFieldInvalid'] = 'The month part is invalid!';
						}
						
						# Check that the day and year fields are numeric
						if ((!is_numeric ($elementValue['day'])) && (!is_numeric ($elementValue['year']))) {
							$elementProblems['dayYearFieldsNotNumeric'] = 'Both the day and year part must be numeric!';
						} else {
							
							# Check that the day is numeric
							if (!is_numeric ($elementValue['day'])) {
								$elementProblems['dayFieldNotNumeric'] = 'The day part must be numeric!';
							}
							
							# Check that the year is numeric
							if (!is_numeric ($elementValue['year'])) {
								$elementProblems['yearFieldNotNumeric'] = 'The year part must be numeric!';
								
							# If the year is numeric, ensure the year has been entered as a two or four digit amount
							} else {
								if ($arguments['autoCenturyConversion']) {
									if ((strlen ($elementValue['year']) != 2) && (strlen ($elementValue['year']) != 4)) {
										$elementProblems['yearInvalid'] = 'The year part must be either two or four digits!';
									}
								} else {
									if (strlen ($elementValue['year']) != 4) {
										$elementProblems['yearInvalid'] = 'The year part must be four digits!';
									}
								}
							}
						}
						
						# If all date parts have been entered correctly, check whether the date is valid
						if (!isSet ($elementProblems)) {
							if (!checkdate (($elementValue['month']), $elementValue['day'], $elementValue['year'])) {
								$elementProblems['dateInvalid'] = 'An invalid date has been entered!';
							}
						}
					}
				}
				
				# If the time is required in addition to the date, parse the time field, allowing flexible input syntax
				if (($arguments['level'] == 'datetime') || ($arguments['level'] == 'time')) {
					
					# Only do time processing if the time field isn't empty
					if (!empty ($elementValue['time'])) {
						
						# If the time parsing passes, substitute the submitted version with the parsed and corrected version
						if ($time = timedate::parseTime ($elementValue['time'])) {
							$elementValue['time'] = $time;
						} else {
							
							# If, instead, the time parsing fails, leave the original submitted version and add the problem to the errors array
							$elementProblems['timePartInvalid'] = 'The time part is invalid!';
						}
					}
				}
			}
		}
		
/*	Not sufficiently tested - results in 31st November 20xx when all set to 0
		# Prevent mktime parameter problems in date processing
		foreach ($elementValue as $key => $value) {
			if ($value === '') {
				$elementValue[$key] = 0;
			}
		}
*/
		
		# Describe restrictions on the widget
		if (($arguments['level'] == 'datetime') || ($arguments['level'] == 'time')) {$restriction = 'Time can be entered flexibly';}
		
		# Start to define the widget's core HTML
		if ($arguments['editable']) {
			$widgetHtml = '';
			
			# Start with the time if required
			if (substr_count ($arguments['level'], 'time')) {	// datetime or time
				$widgetHtml .= "\n\t\t\t\t" . '<span class="' . (!isSet ($elementProblems['timePartInvalid']) ? 'comment' : 'warning') . '">t:&nbsp;</span>';
				$widgetHtml .= '<input' . $this->nameIdHtml ($arguments['name'], false, 'time', true) . ' type="text" size="10" value="' . $elementValue['time'] . '"' . $widget->tabindexHtml () . ' />';
			}
			
			# Add the date and month input boxes; if the day or year are 0 then nothing will be displayed
			if (substr_count ($arguments['level'], 'date')) {	// datetime or date
				$widgetHtml .= "\n\t\t\t\t" . '<span class="comment">d:&nbsp;</span><input' . $this->nameIdHtml ($arguments['name'], false, 'day', true) . ' size="2" maxlength="2" value="' . (($elementValue['day'] != '00') ? $elementValue['day'] : '') . '"' . ($arguments['level'] == 'date' ? $widget->tabindexHtml () : '') . ' />&nbsp;';
				$widgetHtml .= "\n\t\t\t\t" . '<span class="comment">m:</span>';
				$widgetHtml .= "\n\t\t\t\t" . '<select' . $this->nameIdHtml ($arguments['name'], false, 'month', true) . '>';
				$widgetHtml .= "\n\t\t\t\t\t" . '<option value="">Select</option>';
				$months = array (1 => 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'June', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
				foreach ($months as $monthNumber => $monthName) {
					$widgetHtml .= "\n\t\t\t\t\t" . '<option value="' . sprintf ('%02s', $monthNumber) . '"' . (($elementValue['month'] == sprintf ('%02s', $monthNumber)) ? ' selected="selected"' : '') . '>' . $monthName . '</option>';
				}
				$widgetHtml .= "\n\t\t\t\t" . '</select>';
			}
			
			# Add the year box
			if ($arguments['level'] != 'time') {
				$widgetHtml .= "\n\t\t\t\t" . ($arguments['level'] != 'year' ? '<span class="comment">y:&nbsp;</span>' : '');
				$widgetHtml .= '<input' . $this->nameIdHtml ($arguments['name'], false, 'year', true) . ' size="4" maxlength="4" value="' . (($elementValue['year'] != '0000') ? $elementValue['year'] : '') . '" ' . ($arguments['level'] == 'year' ? $widget->tabindexHtml () : '') . '/>' . "\n\t\t";
			}
			
			# Surround with a fieldset if necessary
			if (substr_count ($arguments['level'], 'date')) {	// datetime or date
				$widgetHtml  = "\n\t\t\t<fieldset>" . $widgetHtml . "\n\t\t\t</fieldset>";
			}
		} else {
			
			# Non-editable version
			$widgetHtml  = timedate::presentDateFromArray ($elementValue, $arguments['level']) . ($isTimestamp ? '<br /><span class="comment">' . (($arguments['level'] != 'time') ? '(Current date' . (($arguments['level'] == 'datetime') ? ' and time' : '') : '(Current time') . ')' . '</span>' : '');
			$widgetHtml .= "\n\t\t\t" . '<input' . $this->nameIdHtml ($arguments['name']) . ' type="hidden" value="' . htmlspecialchars ($arguments['default']) . '" />';
		}
		
		# Re-assign back the value
		$this->form[$arguments['name']] = $elementValue;
		
		# Get the posted data
		if ($this->formPosted) {
			
			# Map the components directly and assemble the elements into a string
			if ($arguments['level'] == 'year') {
				unset ($this->form[$arguments['name']]['day']);
				unset ($this->form[$arguments['name']]['month']);
			}
			$data['rawcomponents'] = $this->form[$arguments['name']];
			
			# Ensure there is a presented and a compiled version
			$data['presented'] = '';
			$data['compiled'] = '';
			
			# If all items are not empty then produce compiled and presented versions
			if (!$allFieldsIncomplete && !isSet ($elementProblems)) {
				
				# Make the compiled version be in SQL format, i.e. YYYY-MM-DD HH:MM:SS
				$data['compiled'] = (($arguments['level'] == 'time') ? $this->form[$arguments['name']]['time'] : $this->form[$arguments['name']]['year'] . (($arguments['level'] == 'year') ? '' : '-' . $this->form[$arguments['name']]['month'] . '-' . sprintf ('%02s', $this->form[$arguments['name']]['day'])) . (($arguments['level'] == 'datetime') ? ' ' . $this->form[$arguments['name']]['time'] : ''));
				
				# Make the presented version in english text
				#!# date () corrupts dates after 2038; see php.net/date. Suggest not re-presenting it if year is too great.
				$data['presented'] = timedate::presentDateFromArray ($this->form[$arguments['name']], $arguments['level']);
			}
		}
		
		# Add the widget to the master array for eventual processing
		$this->elements[$arguments['name']] = array (
			'type' => __FUNCTION__,
			'html' => $arguments['prepend'] . $widgetHtml . $arguments['append'],
			'title' => $arguments['title'],
			'description' => $arguments['description'],
			'restriction' => (isSet ($restriction) && $arguments['editable'] ? $restriction : false),
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $arguments['required'],
			'requiredButEmpty' => $requiredButEmpty,
			'suitableAsEmailTarget' => false,
			'output' => $arguments['output'],
			'discard' => $arguments['discard'],
			'data' => (isSet ($data) ? $data : NULL),
			'datatype' => ($arguments['datatype'] ? $arguments['datatype'] : "`{$arguments['name']}` " . strtoupper ($arguments['level'])) . ($arguments['required'] ? ' NOT NULL' : '') . " COMMENT '" . (addslashes ($arguments['title'])) . "'",
		);
	}
	
	
	/**
	 * Create an upload widget set
	 * Note that, for security reasons, browsers do not support setting an initial value.
	 * @param array $arguments Supplied arguments - see template
	 */
	function upload ($suppliedArguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentDefaults = array (
			'name'					=> NULL,	# Name of the element
			'title'					=> '',		# Introductory text
			'description'			=> '',		# Description text
			'default'				=> false,	# Default value(s) (optional), i.e. the current filename(s) if any
			'editable'				=> true,	# Whether the widget is editable (if not, a hidden element will be substituted but the value displayed)
			'append'				=> '',		# HTML appended to the widget
			'prepend'				=> '',		# HTML prepended to the widget
			'output'				=> array (),# Presentation format
			'directory'				=> NULL,	# Path to the file; any format acceptable
			'subfields'				=> 1,		# The number of widgets within the widget set (i.e. available file slots)
			'required'				=> 0,		# The minimum number which must be selected (defaults to 0)
			'size'					=> 30,		# Visible size (optional; defaults to 30)
			'disallowedExtensions'	=> array (),# Simple array of disallowed file extensions (Single-item string also acceptable)
			'allowedExtensions'		=> array (),# Simple array of allowed file extensions (Single-item string also acceptable; '*' means extension required)
			'mime'					=> false,	# Whether to enable the MIME Type check
			'enableVersionControl'	=> true,	# Whether uploading a file of the same name should result in the earlier file being renamed
			'forcedFileName'		=> false,	# Force to a specific filename
			'lowercaseExtension'	=> false,	# Force the file extension to be lowercased
			'discard'				=> false,	# Whether to process the input but then discard it in the results; note that the file will still be uploaded
			'datatype'				=> false,	# Datatype used for database writing emulation (or caching an actual value)
			#!# Consider a way of adding a checkbox to confirm on a per-widget basis; adds quite a few complications though
			'unzip'					=> false,	# Whether to unzip a zip file on arrival, either true/false or the number of files (defaulting to $this->settings['listUnzippedFilesMaximum']) which should be listed in any visible result output
			'attachments'			=> $this->settings['attachments'],	# Whether to send uploaded file(s) as attachment(s) (they will not be unzipped)
			'attachmentsDeleteIfMailed'	=> $this->settings['attachmentsDeleteIfMailed'],	# Whether to delete the uploaded file(s) if successfully mailed
			#!# Change to default to true in a later release once existing applications migrated over
			'flatten'				=> false,	# Whether to flatten the rawcomponents (i.e. default in 'processing' mode) result if only a single subfield is specified
			'tabindex'				=> false,	# Tabindex if required; replace with integer between 0 and 32767 to create
		);
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, __FUNCTION__);
		
		$arguments = $widget->getArguments ();
		
		# Deal with handling of default file specification
		if ($arguments['default']) {
			$arguments['default'] = application::ensureArray ($arguments['default']);
			
			# Ensure there are not too many default files
			if (count ($arguments['default']) > $arguments['subfields']) {
				$this->formSetupErrors['uploadsMismatch'] = "More default files than there are fields available were supplied for the <strong>{$arguments['name']}</strong> file upload element.";
				return false;
			}
			
			# Reorganise any defaults into the same hierarchy as would be posted by the form (rather than just being a single-dimensional array of names) and discard all other supplied info
			$confirmedDefault = array ();
			for ($subfield = 0; $subfield < $arguments['subfields']; $subfield++) {
				if (isSet ($arguments['default'][$subfield])) {
					if (strlen ($arguments['default'][$subfield])) {	// i.e. ensure there is actually a filename
						$confirmedDefault[$subfield] = array (
							'name'		=> $arguments['default'][$subfield],
							'type'		=> NULL,
							'tmp_name'	=> NULL,
							'size'		=> NULL,
							'_source'	=> 'default',
						);
					}
				}
			}
			$arguments['default'] = $confirmedDefault;	// Overwrite the original supplied simple array with the new validated multi-dimensional (or empty) array
		}
		
		# Obtain the value of the form submission (which may be empty)
		#!# NB The equivalent of this line was not present before refactoring
		$widget->setValue (isSet ($this->form[$arguments['name']]) ? $this->form[$arguments['name']] : array ());
		
		$elementValue = $widget->getValue ();
		
		# Ensure that the POST method is being used, as apparently required by RFC1867 and by PHP
		if ($this->method != 'post') {
			$this->formSetupErrors['uploadsRequirePost'] = 'File uploads require the POST method to be used in the form, so either the get setting or the upload widgets should be removed.';
			return false;	// Discontinue further checks
		}
		
		# Check whether unzipping is supported
		if ($arguments['unzip'] && !extension_loaded ('zip')) {
			$this->formSetupErrors['uploadUnzipUnsupported'] = 'Unzipping of zip files upon upload was requested but the unzipping module is not available on this server.';
			$arguments['unzip'] = false;
		}
		
		# Ensure the initial value(s) is an array, even if only an empty one, converting if necessary, and lowercase (and then unique) the extensions lists, ensuring each starts with .
		$arguments['disallowedExtensions'] = application::ensureArray ($arguments['disallowedExtensions']);
		foreach ($arguments['disallowedExtensions'] as $index => $extension) {
			$arguments['disallowedExtensions'][$index] = (substr ($extension, 0, 1) != '.' ? '.' : '') . strtolower ($extension);
		}
		$arguments['disallowedExtensions'] = application::ensureArray ($arguments['disallowedExtensions']);
		$arguments['allowedExtensions'] = array_unique ($arguments['allowedExtensions']);
		foreach ($arguments['allowedExtensions'] as $index => $extension) {
			$arguments['allowedExtensions'][$index] = (substr ($extension, 0, 1) != '.' ? '.' : '') . strtolower ($extension);
		}
		$arguments['allowedExtensions'] = array_unique ($arguments['allowedExtensions']);
		
		# Ensure zip files can be uploaded if unzipping is enabled, by adding it to the list of allowed extensions if such a list is defined
		#!# Allowing zip files but having a list of allowed extensions means that people can zip up a non-allowed extension
		if ($arguments['unzip'] && $arguments['allowedExtensions'] && !in_array ('zip', $arguments['allowedExtensions'])) {
			$arguments['allowedExtensions'][] = 'zip';
		}
		
		# Determine whether a file extension must be included - this is if * is the only value for $arguments['allowedExtensions']; if so, also clear the array
		$extensionRequired = false;
		if (count ($arguments['allowedExtensions']) == 1) {
			if ($arguments['allowedExtensions'][0] == '*') {
				$extensionRequired = true;
				$arguments['allowedExtensions'] = array ();
			}
		}
		
		# Do not allow defining of both disallowed and allowed extensions at once, except for the special case of defining disallowed extensions plus requiring an extension
		if ((!empty ($arguments['disallowedExtensions'])) && (!empty ($arguments['allowedExtensions'])) && (!$extensionRequired)) {
			$this->formSetupErrors['uploadExtensionsMismatch'] = "You cannot, in the <strong>{$arguments['name']}</strong> upload element, define <em>both</em> disallowed <em>and</em> allowed extensions.";
		}
		
		# Check that the number of available subfields is a whole number and that it is at least 1 (the latter error message overrides the first if both apply, e.g. 0.5)
		if ($arguments['subfields'] != round ($arguments['subfields'])) {$this->formSetupErrors['uploadSubfieldsIncorrect'] = "You specified a non-whole number (<strong>{$arguments['subfields']}</strong>) for the number of file upload widgets in the <strong>{$arguments['name']}</strong> upload element which the form should create.";}
		if ($arguments['subfields'] < 1) {$this->formSetupErrors['uploadSubfieldsIncorrect'] = "The number of files to be uploaded must be at least one; you specified <strong>{$arguments['subfields']}</strong> for the <strong>{$arguments['name']}</strong> upload element.";}
		
		# Explicitly switch off flattening if there is not a singular subfield
		if ($arguments['subfields'] != 1) {$arguments['flatten'] = false;}
		
		# Check that the minimum required is a whole number and that it is not greater than the number actually available
		if ($arguments['required'] != round ($arguments['required'])) {$this->formSetupErrors['uploadSubfieldsMinimumIncorrect'] = "You specified a non-whole number (<strong>{$arguments['required']}</strong>) for the number of file upload widgets in the <strong>{$arguments['name']}</strong> upload element which must the user must upload.";}
		if ($arguments['required'] > $arguments['subfields']) {$this->formSetupErrors['uploadSubfieldsMinimumMismatch'] = "The required minimum number of files which the user must upload (<strong>{$arguments['required']}</strong>) specified in the <strong>{$arguments['name']}</strong> upload element is above the number of files actually available to be specified for upload (<strong>{$arguments['subfields']}</strong>).";}
		
		# Check that the selected directory exists and is writable (or create it)
		if ($arguments['directory']) {
			if (!is_dir ($arguments['directory']) || !is_writeable ($arguments['directory'])) {
				if (!application::directoryIsWritable ($arguments['directory'])) {
					$this->formSetupErrors['directoryNotWritable'] = "The directory specified for the <strong>{$arguments['name']}</strong> upload element is not writable. Please check that the file permissions to ensure that the webserver 'user' can write to the directory.";
				} else {
					#!# Third parameter doesn't exist in PHP4 - will this cause a crash?
					umask (0);
					mkdir ($arguments['directory'], $this->settings['directoryPermissions'], $recursive = true);
				}
			}
		}
		
		# Check that, if MIME Type checking is wanted, and the file extension check is in place, that all are supported
		$mimeTypes = array ();
		if ($arguments['mime']) {
			if (!$arguments['allowedExtensions']) {
				$this->formSetupErrors['uploadMimeNoExtensions'] = "MIME Type checking was requested but allowedExtensions has not been set.";
				$arguments['mime'] = false;
			}
			if (!function_exists ('mime_content_type')) {
				$this->formSetupErrors['uploadMimeExtensionsMismatch'] = "MIME Type checking was requested but is not available on this server platform.";
				$arguments['mime'] = false;
			} else {
				$this->mimeTypes = application::mimeTypeExtensions ();
				if ($arguments['allowedExtensions']) {
					$inBoth = array_intersect ($arguments['allowedExtensions'], array_keys ($this->mimeTypes));
					if (count ($inBoth) != count ($arguments['allowedExtensions'])) {
						$arguments['mime'] = false;	// Disable execution of the mime block below
						$this->formSetupErrors['uploadMimeExtensionsMismatch'] = "MIME Type checking was requested for the <strong>{$arguments['name']}</strong> upload element, but not all of the allowedExtensions are supported in the MIME checking list";
					}
				}
				foreach ($arguments['allowedExtensions'] as $extension) {
					$mimeTypes[] = $this->mimeTypes[$extension];
				}
			}
		}
		
		# Prevent more files being uploaded than the number of form elements (this is not strictly necessary, however, as the subfield looping below prevents the excess being processed)
		if (count ($elementValue) > $arguments['subfields']) {
			$elementProblems['subfieldsMismatch'] = 'You appear to have submitted more files than there are fields available.';
		}
		
		# Start the HTML
		$widgetHtml = '';
		if ($arguments['subfields'] > 1) {$widgetHtml .= "\n\t\t\t";}
		
		# Loop through the number of fields required to create the widget and perform checks
		$apparentlyUploadedFiles = array ();
		if ($arguments['default']) {$apparentlyUploadedFiles = $arguments['default'];}	// add in the numerically-indexed defaults (which are then overwritten if more uploaded)
		for ($subfield = 0; $subfield < $arguments['subfields']; $subfield++) {
			
			# Continue further processing if the file has been uploaded
			if (isSet ($elementValue[$subfield]) && is_array ($elementValue[$subfield]) && array_key_exists ('name', $elementValue[$subfield])) {	// 'name' should always exist but it won't if a form spammer submits this as an input rather than upload
				
				# Add the apparently uploaded file (irrespective of whether it passes other checks)
				$elementValue[$subfield]['_directory'] = $arguments['directory'];	// Cache the directory for later use
				$elementValue[$subfield]['_attachmentsDeleteIfMailed'] = $arguments['attachmentsDeleteIfMailed'];
				$apparentlyUploadedFiles[$subfield] = $elementValue[$subfield];
				
				# If an extension is required but the submitted item doesn't contain a dot, throw a problem
				if (($extensionRequired) && (strpos ($elementValue[$subfield]['name'], '.') === false)) {
					$extensionsMissing[] = $elementValue[$subfield]['name'];
				} else {
					
					# If the file is not valid, add it to a list of invalid subfields
					if (!application::filenameIsValid ($elementValue[$subfield]['name'], $arguments['disallowedExtensions'], $arguments['allowedExtensions'])) {
						$filenameInvalidSubfields[] = $elementValue[$subfield]['name'];
					}
				}
			}
			
			# Where default file(s) are/is expected, show - for the current subfield - the filename for each file (or that there is no file)
			if ($arguments['default']) {
				$widgetHtml .= '<p class="currentfile' . ($subfield > 0 ? ' currentfilenext' : '') . '">' . (isSet ($arguments['default'][$subfield]) ? 'Current file: <span class="filename">' . htmlspecialchars (basename ($arguments['default'][$subfield]['name'])) . '</span>' : '<span class="comment">(No current file)</span>') . "</p>\n\t\t\t";
			}
			
			# Define the widget's core HTML; note that MAX_FILE_SIZE as mentioned in the PHP manual is bogus (non-standard and seemingly not supported by any browsers), so is not supported here - doing so would also require MAX_FILE_SIZE as a disallowed form name, and would expose to the user the size of the PHP ini setting
			// $widgetHtml .= '<input type="hidden" name="MAX_FILE_SIZE" value="' . application::convertSizeToBytes (ini_get ('upload_max_filesize')) . '" />';
			if ($arguments['editable']) {
				$widgetHtml .= '<input' . $this->nameIdHtml ($arguments['name'], false, $subfield, true) . " type=\"file\" size=\"{$arguments['size']}\"" . $widget->tabindexHtml ($subfield) . ($mimeTypes ? ' accept="' . implode (', ', $mimeTypes) . '"' : '') . ' />';
				$widgetHtml .= (($subfield != ($arguments['subfields'] - 1)) ? "<br />\n\t\t\t" : (($arguments['subfields'] == 1) ? '' : "\n\t\t"));
			} else {
				if ($arguments['default'] && isSet ($arguments['default'][$subfield])) {
					$widgetHtml .= '<input' . $this->nameIdHtml ($arguments['name'], false, $subfield, true) . ' type="hidden" value="' . htmlspecialchars (basename ($arguments['default'][$subfield]['name'])) . '" />' . "\n\t\t\t";
				}
			}
		}
		
		# Append the description where default filename(s) are supplied
		if ($arguments['default'] && $arguments['editable']) {
			$arguments['description'] = 'Entering a new file will replace the current reference' . ($arguments['description'] ? ". {$arguments['description']}" : '');	// Note that the form itself does not handle file deletions (except for natural overwrites), because the 'default' is just a string coming from $data
		}
		
		# If fields which don't have a file extension have been found, throw a user error
		if (isSet ($extensionsMissing)) {
			$elementProblems['fileExtensionAbsent'] = (count ($extensionsMissing) > 1 ? 'The files <em>' : 'The file <em>') . implode ('</em>, <em>', $extensionsMissing) . (count ($extensionsMissing) > 1 ? '</em> have' : '</em> has') . ' no file extension, but file extensions are required for files selected in this section.';
		}
		
		# If fields which have an invalid extension have been found, throw a user error
		if (isSet ($filenameInvalidSubfields)) {
			$elementProblems['fileExtensionMismatch'] = (count ($filenameInvalidSubfields) > 1 ? 'The files <em>' : 'The file <em>') . implode ('</em>, <em>', $filenameInvalidSubfields) . (count ($filenameInvalidSubfields) > 1 ? '</em> do not' : '</em> does not') . ' comply with the specified file extension rules for this section.';
		}
		
		# If fields which have an invalid MIME Type have been found, throw a user error
		if (isSet ($filenameInvalidMimeTypes)) {
			$elementProblems['fileMimeTypeMismatch'] = (count ($filenameInvalidMimeTypes) > 1 ? 'The files <em>' : 'The file <em>') . implode ('</em>, <em>', $filenameInvalidMimeTypes) . (count ($filenameInvalidMimeTypes) > 1 ? '</em> do not' : '</em> does not') . ' appear to be valid.';
		}
		
		# If any files have been uploaded, the user will need to re-select them.
		$totalApparentlyUploadedFiles = count ($apparentlyUploadedFiles);	// This will include the defaults, some of which might have been overwritten
		if ($totalApparentlyUploadedFiles > 0) {
			$this->elementProblems['generic']['reselectUploads'] = "You will need to reselect the " . ($totalApparentlyUploadedFiles == 1 ? 'file' : "{$totalApparentlyUploadedFiles} files") . " you selected for uploading, because of problems elsewhere in the form. (Re-selection is a security requirement of your web browser.)";
		}
		
		# Check if the field is required (i.e. the minimum number of fields is greater than 0) and, if so, run further checks
		if ($required = ($arguments['required'] > 0)) {
			
			# If none have been uploaded, class this as requiredButEmpty
			if ($totalApparentlyUploadedFiles == 0) {
				$requiredButEmpty = true;
				
			# If too few have been uploaded, produce a individualised warning message
			} else if ($totalApparentlyUploadedFiles < $arguments['required']) {
				$elementProblems['underMinimum'] = ($arguments['required'] != $arguments['subfields'] ? 'At least' : 'All') . " <strong>{$arguments['required']}</strong> " . ($arguments['required'] > 1 ? 'files' : 'file') . ' must be submitted; you will need to reselect the ' . ($totalApparentlyUploadedFiles == 1 ? 'file' : "{$totalApparentlyUploadedFiles} files") . ' that you did previously select, for security reasons.';
			}
		}
		
		# Describe a restriction on the widget for minimum number of uploads
		if ($arguments['required'] > 1) {$restrictions[] = "Minimum {$arguments['required']} items required";}
		
		# Describe extension restrictions on the widget and compile them as a semicolon-separated list
		if ($extensionRequired) {
			$restrictions[] = 'File extensions are required';
		} else {
			if (!empty ($arguments['allowedExtensions'])) {
				$restrictions[] = 'Allowed file extensions: ' . implode (',', $arguments['allowedExtensions']);
			}
		}
		if (!empty ($arguments['disallowedExtensions'])) {
			$restrictions[] = 'Disallowed file extensions: ' . implode (',', $arguments['disallowedExtensions']);
		}
		if ($arguments['unzip']) {
			$restrictions[] = 'Zip files will be automatically unzipped on arrival.';
		}
		if (isSet ($restrictions)) {$restrictions = implode (";\n", $restrictions);}
		
		# Assign half-validated data, for the purposes of the groupValidation check; note that this could be tricked, but should be good enough in most cases, and certainly better than nothing
		$data['presented'] = $totalApparentlyUploadedFiles;
		#!# This is a workaround for when using getUnfinalisedData, to prevent offsets
		$data['rawcomponents'] = $totalApparentlyUploadedFiles;
		
		# Register the attachments, and disable unzipping
		#!# Ideally unzipping should be done after a zip file is e-mailed, but this would require much refactoring of the output processing, i.e. (i) upload, (ii) attach attachments, (iii) unzip
		if ($arguments['attachments']) {
			$this->attachments = array_merge ($this->attachments, $apparentlyUploadedFiles);
			$this->uploadProperties[$arguments['name']]['unzip'] = false;
			$arguments['unzip'] = false;
		}
		
		# Re-assign back the value
		$this->form[$arguments['name']] = $elementValue;
		
		# Cache the upload properties
		$this->uploadProperties[$arguments['name']] = $arguments;
		
		# Add the widget to the master array for eventual processing
		$this->elements[$arguments['name']] = array (
			'type' => __FUNCTION__,
			'html' => $arguments['prepend'] . $widgetHtml . $arguments['append'],
			'title' => $arguments['title'],
			'description' => $arguments['description'],
			'restriction' => (isSet ($restrictions) ? $restrictions : false),
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $required,
			'requiredButEmpty' => (isSet ($requiredButEmpty) ? $requiredButEmpty : false),
			'suitableAsEmailTarget' => false,
			'output' => $arguments['output'],
			'flatten' => $arguments['flatten'],
			'discard' => $arguments['discard'],
			'data' => $data,	// Because the uploading can only be processed later, this is set to NULL
			#!# Not finished
#			'datatype' => ($arguments['datatype'] ? $arguments['datatype'] : "`{$arguments['name']}` " . 'VARCHAR (255)') . ($arguments['required'] ? ' NOT NULL' : '') . " COMMENT '" . (addslashes ($arguments['title'])) . "'",
			'unzip'	=> $arguments['unzip'],
			'mime' => $arguments['mime'],
			'subfields' => $arguments['subfields'],
			'default'	=> $arguments['default'],
		);
	}
	
	
	/**
	 * Function to pass hidden data over
	 * @param array $arguments Supplied arguments - see template
	 */
	function hidden ($suppliedArguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentDefaults = array (
			'name'			=> 'hidden',				# Name of the element (Optional)
			'values'				=> array (),		# Associative array of selectable values
			'output'				=> array (),		# Presentation format
			'title'					=> 'Hidden data',	# Title (CURRENTLY UNDOCUMENTED)
			'security'				=> true, 			# Whether to ignore posted data and use the internal values set, for security (only of relevance to non- self-processing forms); probably only switch off when using javascript to modify a value and submit that
			'discard'				=> false,	# Whether to process the input but then discard it in the results
			'datatype'				=> false,	# Datatype used for database writing emulation (or caching an actual value)
		);
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, __FUNCTION__);
		
		$arguments = $widget->getArguments ();
		
		# Flag that a hidden element is present
		$this->hiddenElementPresent = true;
		
		# Check that the values array is actually an array, containing elements within it
		if (!is_array ($arguments['values']) || empty ($arguments['values'])) {$this->formSetupErrors['hiddenElementNotArray'] = "The hidden data specified for the <strong>{$arguments['name']}</strong> hidden input element must be an array of values but is not currently.";}
		
		# Create the HTML by looping through the data array; this is only of use to non- self-processing forms, i.e. where the data is sent elsewhere; for self-processing the submitted data is ignored
		$widgetHtml = "\n";
		foreach ($arguments['values'] as $key => $value) {
			$widgetHtml .= "\n\t" . '<input type="hidden"' . $this->nameIdHtml ($arguments['name'], false, $key, true) . ' value="' . $value . '" />';
		}
		$widgetHtml .= "\n";
		
		# Get the posted data
		if ($this->formPosted) {
			
/*
			#!# Removed - needs to be tested properly first
			# Throw a fake submission warning if the posted data (which is later ignored anyway) does not match the assigned data
			if ($arguments['security']) {
				if ($this->form[$arguments['name']] !== $arguments['values']) {
					$elementProblems['hiddenFakeSubmission'] = 'The hidden data which was submitted did not match that which was set. This appears to have been a faked submission.';
				}
			}
*/
			
			# Map the components onto the array directly and assign the compiled version; no attempt is made to combine the data
			$data['rawcomponents'] = ($arguments['security'] ? $arguments['values'] : $this->form[$arguments['name']]);
			
			# The presented version is just an empty string
			$data['presented'] = '';
		}
		
		# Add the widget to the master array for eventual processing
		$this->elements[$arguments['name']] = array (
			'type' => __FUNCTION__,
			'html' => $widgetHtml,
			'title' => $arguments['title'],
			'values' => $arguments['values'],
			'description' => false,
			'restriction' => false,
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => true,
			'requiredButEmpty' => false,
			'suitableAsEmailTarget' => false,
			'output' => $arguments['output'],
			'discard' => $arguments['discard'],
			'data' => (isSet ($data) ? $data : NULL),
			#!# Not finished
			#!# 'datatype' => ($arguments['datatype'] ? $arguments['datatype'] : "`{$arguments['name']}` " . 'VARCHAR (255)') . ($arguments['required'] ? ' NOT NULL' : '') . " COMMENT '" . (addslashes ($arguments['title'])) . "'",
		);
	}
	
	
	/**
	 * Function to allow text headings or paragraphs
	 * @param string $level Name of the element Level, e.g. 1 for <h1></h1>, 2 for <h2></h2>, etc., 'p' for <p></p>, or 'text' for text without any markup added
	 * @param string $title Text
	 */
	function heading ($level, $title)
	{
		# Add the headings as text
		switch ($level) {
			case '0':
			case 'p':
				$widgetHtml = "<p>{$title}</p>";
				break;
			case 'text':
			case '':
				$widgetHtml = $title;
				break;
			default:
				$widgetHtml = "<h{$level}>{$title}</h{$level}>";
				break;
		}
		
		# Add the widget to the master array for eventual processing
		$this->elements['_heading' . $this->headingTextCounter++] = array (
			'type' => __FUNCTION__,
			'html' => $widgetHtml,
			'title' => '',
			'description' => false,
			'restriction' => false,
			'problems' => false, #!# Should ideally be getElementProblems but can't create an object as no real parameters to supply
			'required' => false,
			'requiredButEmpty' => false,
			'suitableAsEmailTarget' => false,
			'output' => array (),	// The output specification must always be array
			'discard' => false,
			'data' => (isSet ($data) ? $data : NULL),
		);
	}
	
	
	# Function to generate ID and name HTML
	function nameIdHtml ($widgetName, $multiple = false, $subitem = false, $nameAppend = false, $idOnly = false)
	{
		# Create the name and ID and compile the HTML
		# http://htmlhelp.com/reference/html40/attrs.html says that "Also note that while NAME may contain entities, the ID attribute value may not."
		# Note also that the <option> tag does not support the NAME attribute
		$widgetNameCleaned = htmlspecialchars ($widgetName);
		$subitemCleaned = htmlspecialchars ($subitem);
		$name = ' name="' .              ($this->settings['name'] ? "{$this->settings['name']}[{$widgetNameCleaned}]" : $widgetName) . ($multiple ? '[]' : '') . ($nameAppend ? "[{$subitemCleaned}]" : '') . '"';
		if ($subitem !== false) {
			$widgetName .= "_{$subitem}";
			if (!strlen ($subitem)) {$widgetName .= '____NULL';}	// #!# Dirty fix - should really have a guarantee of uniqueness
		}
		$id   = ' id="' . $this->cleanId ($this->settings['name'] ? "{$this->settings['name']}[{$widgetName}]" : $widgetName) . '"';
		$html = ($idOnly ? '' : $name) . $id;
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to clean an HTML id attribute
	function cleanId ($id)
	{
		# Replace non-allowed characters
		# http://htmlhelp.com/reference/html40/attrs.html states:
		# - "Also note that while NAME may contain entities, the ID attribute value may not."
		# - "The attribute's value must begin with a letter in the range A-Z or a-z and may be followed by letters (A-Za-z), digits (0-9), hyphens ("-"), underscores ("_"), colons (":"), and periods ("."). The value is case-sensitive."
		$id = preg_replace ('/[^-_:.a-zA-Z0-9]/','_', $id);	// The unicode semantics flag /u is NOT enabled, as this makes the function return false when a non-Unicode string is added
		
		# Ensure the first character is valid
		#!# Currently this routine doesn't ensure that the first is A-Z or a-z, though often the elements will have form_ added anyway
		
		# Chop off any trailing _
		while (substr ($id, -1) == '_') {
			$id = substr ($id, 0, -1);
		}
		
		# Return the cleaned ID
		return $id;
	}
	
	
	# Function to ensure that all initial values are in the array of values
	function ensureDefaultsAvailable ($arguments)
	{
		# Convert to an array (for this local function only) if not already
		if (!is_array ($arguments['default'])) {
			$arguments['default'] = application::ensureArray ($arguments['default']);
		}
		
		# Ensure values are not duplicated
		if (count ($arguments['default']) != count (array_unique ($arguments['default']))) {
			$this->formSetupErrors['defaultContainsDuplicates'] = "In the <strong>{$arguments['name']}</strong> element, the default values contain duplicates.";
		}
		
		# For an array of defaults, check through each
		foreach ($arguments['default'] as $defaultValue) {
			if (!in_array ($defaultValue, array_keys ($arguments['values']))) {
				$missingValues[] = $defaultValue;
			}
		}
		
		# Construct the warning message
		if (isSet ($missingValues)) {
			$totalMissingValues = count ($missingValues);
			$this->formSetupErrors['defaultMissingFromValuesArray'] = "In the <strong>{$arguments['name']}</strong> element, the default " . ($totalMissingValues > 1 ? 'values ' : 'value ') . implode (', ', $missingValues) . ($totalMissingValues > 1 ? ' were' : ' was') . ' not found in the list of available items for selection by the user.';
		}
	}
	
	
	# Function to ensure that values are associative, even if multidimensional
	#!# This function should be in the widget class but that won't work until formSetupErrors carry back to the main class
	function ensureHierarchyAssociative ($originalValues, $forceAssociative, $elementName)
	{
		# End if no values
		if (!$originalValues) {return false;}
		
		# Convert the values, at any hierarchical level, to being associative
		if (!$values = application::ensureValuesArrangedAssociatively ($originalValues, $forceAssociative)) {
			$this->formSetupErrors['hierarchyTooDeep'] = "Multidimensionality is supported only to one level deep, but more levels than this were found in the <strong>$elementName</strong> element.";
			return $originalValues;
		}
		
		# Create a list of keys to ensure there are no duplicated keys
		$keys = array ();
		foreach ($values as $key => $value) {
			$keys = array_merge ($keys, (is_array ($value) ? array_keys ($value) : array ($key)));
		}
		if (count ($keys) != count (array_unique ($keys))) {
			$this->formSetupErrors['multidimensionalKeyClash'] = "Some of the multidimensional keys in the <strong>$elementName</strong> element clash with other keys elsewhere in the hierarchy. Fix this by changing the key names, possibly by switching on forceAssociative.";
			return $originalValues;
		}
		
		# Return the arranged values
		return $values;
	}
	
	
	# Function to determine whether an array of values for a select form is suitable as an e-mail target
	function _suitableAsEmailTarget ($values, $arguments)
	{
		# If it's not a required field, it's not suitable
		if (!$arguments['required']) {return 'the field is not set as a required field';}
		
		# If it's multiple and more than one is required, it's not suitable
		if ($arguments['multiple'] && ($arguments['required'] > 1)) {return 'the field allows multiple values to be selected';}
		
		# If it's set as uneditable but there is not exactly one default, it's not suitable
		if (!$arguments['editable'] && count ($arguments['default']) !== 1) {return 'the field is set as uneditable but a single default value has not been supplied';}
		
		# Return true if all e-mails are valid
		if (application::validEmail ($values)) {return true;}
		
		# If any are prefixes which when suffixed would not be valid as an e-mail, then flag this
		foreach ($values as $value) {
			if (!application::validEmail ($value . '@example.com')) {
				return 'not all values available would expand to a valid e-mail address';
			}
		}
		
		# Otherwise return a special keyword that a suffix would be required
		return '_suffixRequired';
	}
	
	
	/**
	 * Function to merge the multi-part posting of files to the main form array, effectively emulating the structure of a standard form input type
	 * @access private
	 */
	function mergeFilesIntoPost ()
	{
		# PHP's _FILES array is (stupidly) arranged differently depending on whether you are using 'formname[elementname]' or just 'elementname' as the element name - see "HTML array feature" note at www.php.net/features.file-upload
		if ($this->settings['name']) {	// i.e. <input name="formname[widgetname]"
			
			# End if no files
			if (empty ($_FILES[$this->settings['name']])) {return;}
			
			# Loop through each upload widget set which has been submitted (even if empty)
			foreach ($_FILES[$this->settings['name']]['name'] as $widgetName => $subElements) {	// 'name' is used but type/tmp_name/error/size could also have been used
				
				# Loop through each upload widget set's subelements (e.g. 4 items if there are 4 input tags within the widget set)
				foreach ($subElements as $elementIndex => $value) {
					
					# Map the file information into the main form element array
					if (!empty ($value)) {
						$this->collection[$this->settings['name']][$widgetName][$elementIndex] = array (
							'name' => $_FILES[$this->settings['name']]['name'][$widgetName][$elementIndex],
							'type' => $_FILES[$this->settings['name']]['type'][$widgetName][$elementIndex],
							'tmp_name' => $_FILES[$this->settings['name']]['tmp_name'][$widgetName][$elementIndex],
							#'error' => $_FILES[$this->settings['name']]['error'][$widgetName][$elementIndex],
							'size' => $_FILES[$this->settings['name']]['size'][$widgetName][$elementIndex],
						);
					}
				}
			}
		} else {	// i.e. <input name="widgetname"
			
			# End if no files
			if (empty ($_FILES)) {return;}
			
			# Loop through each upload widget set which has been submitted (even if empty); note that _FILES is arranged differently depending on whether you are using 'formname[elementname]' or just 'elementname' as the element name - see "HTML array feature" note at www.php.net/features.file-upload
			foreach ($_FILES as $widgetName => $aspects) {
				
				# Loop through each sub element
				foreach ($aspects['name'] as $elementIndex => $value) {
					
					# Map the file information into the main form element array
					if (!empty ($value)) {
						$this->collection[$widgetName][$elementIndex] = array (
							'name' => $_FILES[$widgetName]['name'][$elementIndex],
							'type' => $_FILES[$widgetName]['type'][$elementIndex],
							'tmp_name' => $_FILES[$widgetName]['tmp_name'][$elementIndex],
							#'error' => $_FILES[$widgetName]['error'][$elementIndex],
							'size' => $_FILES[$widgetName]['size'][$elementIndex],
						);
					}
				}
			}
		}
	}
	
	
	## Helper functions ##
	
	
	/**
	 * Wrapper function to dump data to the screen
	 * @access public
	 */
	function dumpData ($data)
	{
		return application::dumpData ($data);
	}
	
	
	/**
	 * Function to show debugging information (configured form elements and submitted form elements) if required
	 * @access private
	 */
	function showDebuggingInformation ()
	{
		# Start the debugging HTML
		$html  = "\n\n" . '<div class="debug">';
		$html .= "\n\n<h2>Debugging information</h2>";
		$html .= "\n\n<ul>";
		$html .= "\n\n\t" . '<li><a href="#configured">Configured form elements - $this->elements</a></li>';
		if ($this->formPosted) {$html .= "\n\n\t" . '<li><a href="#submitted">Submitted form elements - $this->form</a></li>';}
		$html .= "\n\n\t" . '<li><a href="#remainder">Any form setup errors; then: Remainder of form</a></li>';
		$html .= "\n\n</ul>";
		
		# Show configured form elements
		$html .= "\n\n" . '<h3 id="configured">Configured form elements - $this->elements :</h3>';
		$html .= $this->dumpData ($this->elements, false, true);
		
		# Show submitted form elements, if the form has been submitted
		if ($this->formPosted) {
			$html .= "\n\n" . '<h3 id="submitted">Submitted form elements - $this->form :</h3>';
			$html .= $this->dumpData ($this->form, false, true);
		}
		
		# End the debugging HTML
		$html .= "\n\n" . '<a name="remainder"></a>';
		$html .= "\n</div>";
		
		# Add the HTML to the master array
		$this->html .= $html;
	}
	
	
	## Deal with form output ##
	
	/**
	 * Output the result as an e-mail
	 */
	function setOutputEmail ($recipient, $administrator = '', $subjectTitle = 'Form submission results', $chosenElementSuffix = NULL, $replyToField = NULL, $displayUnsubmitted = true)
	{
		# Flag that this method is required
		$this->outputMethods['email'] = true;
		
		# Flag whether to display as empty (rather than absent) those widgets which are optional and have had nothing submitted
		$this->configureResultEmailShowUnsubmitted = $displayUnsubmitted;
		
		# If the recipient is an array, split it into a recipient as the first and cc: as the remainder:
		if (is_array ($recipient)) {
			$recipientList = $recipient;
			$recipient = array_shift ($recipientList);
			$this->configureResultEmailCc = $recipientList;
		}
		
		# Assign the e-mail recipient
		$this->configureResultEmailRecipient = $this->_setRecipient ($recipient, $chosenElementSuffix);
		
		# Assign the administrator by default to $administrator; if none is specified, use the SERVER_ADMIN, otherwise use the supplied administrator if that is a valid e-mail address
		$this->configureResultEmailAdministrator = $this->_setAdministrator ($administrator);
		
		# Set the reply-to field if applicable
		$this->configureResultEmailReplyTo = $this->_setReplyTo ($replyToField);
		
		# Assign the subject title, replacing a match for {fieldname} with the contents of the fieldname, which must be an 'input' widget type
		$this->configureResultEmailedSubjectTitle['email'] = $subjectTitle;
		if (preg_match ('/\{([^\} ]+)\}/', $subjectTitle, $matches)) {
			$element = $matches[1];
			if (isSet ($this->elements[$element]) && ($this->elements[$element]['type'] == 'input')) {
				$this->configureResultEmailedSubjectTitle['email'] = str_replace ('{' . $element . '}', $this->elements[$element]['data']['presented'], $subjectTitle);
			}
		}
		
		#!# This cleaning routine is not a great fix but at least helps avoid ugly e-mail subject lines for now
		//$this->configureResultEmailedSubjectTitle['email'] = html_entity_decode (application::htmlentitiesNumericUnicode ($this->configureResultEmailedSubjectTitle['email']), ENT_COMPAT, 'UTF-8');
	}
	
	
	# Helper function called by setOutputEmail to set the recipient
	function _setRecipient ($recipient, $chosenElementSuffix)
	{
		# If the recipient is a valid e-mail address then use that; if not, it should be a field name
		if (application::validEmail ($recipient)) {
			return $recipient;
		}
		
		# If the recipient is supposed to be a form field, check that field exists
		if (!isSet ($this->elements[$recipient])) {
			$this->formSetupErrors['setOutputEmailElementNonexistent'] = "The chosen field (<strong>$recipient</strong>) (which has been specified as an alternative to a valid e-mail address) for the recipient's e-mail does not exist.";
			return false;
		}
		
		# If the field type is not suitable as an e-mail target, throw a setup error
		if (!$this->elements[$recipient]['suitableAsEmailTarget']) {
			$this->formSetupErrors['setOutputEmailElementInvalid'] = "The chosen field (<strong>$recipient</strong>) is not a valid field from which the recipient of the result-containing e-mail can be taken.";
			return false;
		}
		
		# If it is exactly suitable, it's now fine; if not there are requirements which must be fulfilled
		if ($this->elements[$recipient]['suitableAsEmailTarget'] === true) {
			return $recipient;
		}
		
		# If, the element suffix is not valid, then disallow
		if ($this->elements[$recipient]['suitableAsEmailTarget'] === '_suffixRequired') {
			
			# No suffix has been supplied
			if (!$chosenElementSuffix) {
				$this->formSetupErrors['setOutputEmailElementSuffixMissing'] = "The chosen field (<strong>$recipient</strong>) for the receipient of the result-containing e-mail must have a suffix supplied within the e-mail output specification.";
				return false;
			}
			
			# If a suffix has been supplied, ensure that it will make a valid e-mail address
			if (!application::validEmail ($chosenElementSuffix, true)) {
				$this->formSetupErrors['setOutputEmailElementSuffixInvalid'] = "The chosen field (<strong>$recipient</strong>) for the receipient of the result-containing e-mail requires a valid @domain suffix.";
				return false;
			}
			
			# As the suffix is confirmed requried and valid, assign the recipient suffix
			$this->configureResultEmailRecipientSuffix = $chosenElementSuffix;
			return $recipient;
		}
		
		# There is therefore some particular configuration that prevents it being so, so explain what this is
		if ($this->elements[$recipient]['suitableAsEmailTarget']) {
			$this->formSetupErrors['setOutputEmailElementWidgetSuffixInvalid'] = "The chosen field (<strong>$recipient</strong>) for the receipient of the result-containing e-mail could not be used because {$this->elements[$recipient]['suitableAsEmailTarget']}.";
			return false;
		}
	}
	
	
	# Helper function called by setOutputEmail to set the administrator
	function _setAdministrator ($administrator)
	{
		# Return the server admin if no administrator supplied
		if (!$administrator) {
			return $_SERVER['SERVER_ADMIN'];
		}
		
		# If an address is supplied, confirm it's valid
		if (application::validEmail ($administrator)) {
			return $administrator;
		}
		
		# If the non-validated address includes an @ but is not a valid address, state this as an error
		if (strpos ($administrator, '@') !== false) {
			$this->formSetupErrors['setOutputEmailReceipientEmailSyntaxInvalid'] = "The chosen e-mail sender address (<strong>$administrator</strong>) contains an @ symbol but is not a valid e-mail address.";
			return false;
		}
		
		# Given that a field name has thus been supplied, check it exists
		if (!isSet ($this->elements[$administrator])) {
			$this->formSetupErrors['setOutputEmailReceipientInvalid'] = "The chosen e-mail sender address (<strong>$administrator</strong>) is a non-existent field name.";
			return false;
		}
		
		# Check it's a valid type to use
		if ($this->elements[$administrator]['type'] != 'email') {
			$this->formSetupErrors['setOutputEmailReceipientInvalidType'] = "The chosen e-mail sender address (<strong>$administrator</strong>) is not an e-mail type field name.";
			return false;
		}
		
		# Otherwise return what was supplied
		return $administrator;
	}
	
	
	# Helper function called by setOutputEmail to set the reply-to field
	function _setReplyTo ($replyToField)
	{
		# Return if not set
		if (!$replyToField) {
			return false;
		}
		
		# If a field is set but it does not exist, throw an error and null the supplied argument
		if (!isSet ($this->elements[$replyToField])) {
			$this->formSetupErrors['setOutputEmailReplyToFieldInvalid'] = "The chosen e-mail reply-to address (<strong>$replyToField</strong>) is a non-existent field name.";
			return NULL;
		}
		
		# If it's not an e-mail or input type, disallow use as the field and null the supplied argument
		if (($this->elements[$replyToField]['type'] != 'email') && ($this->elements[$replyToField]['type'] != 'input')) {
			$this->formSetupErrors['setOutputEmailReplyToFieldInvalidType'] = "The chosen e-mail reply-to address (<strong>$replyToField</strong>) is not an e-mail/input type field name.";
			return NULL;
		}
		
		# Return the result
		return $replyToField;
	}
	
	
	/**
	 * Output a confirmation of the submitted results to the submitter
	 */
	function setOutputConfirmationEmail ($chosenelementName, $administrator = '', $subjectTitle = 'Form submission results', $includeAbuseNotice = true, $displayUnsubmitted = true)
	{
		# Flag that this method is required
		$this->outputMethods['confirmationEmail'] = true;
		
		# Flag whether to display as empty (rather than absent) those widgets which are optional and have had nothing submitted
		$this->configureResultConfirmationEmailShowUnsubmitted = $displayUnsubmitted;
		
		# Throw a setup error if the element name for the chosen e-mail field doesn't exist or it is not an e-mail type
		#!# Allow text-field types to be used if a hostname part is specified, or similar
		if (!isSet ($this->elements[$chosenelementName])) {
			$this->formSetupErrors['setOutputConfirmationEmailElementNonexistent'] = "The chosen field (<strong>$chosenelementName</strong>) for the submitter's confirmation e-mail does not exist.";
		} else {
			if ($this->elements[$chosenelementName]['type'] != 'email') {
				$this->formSetupErrors['setOutputConfirmationEmailTypeMismatch'] = "The chosen field (<strong>$chosenelementName</strong>) for the submitter's confirmation e-mail is not an e-mail field type.";
			} else {
				
				# If the form has been posted and the relevant element is assigned, assign the recipient (i.e. the submitter's) e-mail address (which is validated by this point)
				if ($this->formPosted) {
					#!# As noted later on, this really must be replaced with a formSetupErrors call here
					if (!empty ($this->form[$chosenelementName])) {
						$this->configureResultConfirmationEmailRecipient = $this->form[$chosenelementName];
					}
				}
			}
		}
		
		# Assign whether to include an abuse report notice
		$this->configureResultConfirmationEmailAbuseNotice = $includeAbuseNotice;
		
		# Assign the administrator e-mail address
		$this->configureResultConfirmationEmailAdministrator = ($administrator != '' ? $administrator : $_SERVER['SERVER_ADMIN']);
		
		# Assign the subject title, replacing a match for {fieldname} with the contents of the fieldname, which must be an 'input' widget type
		$this->configureResultEmailedSubjectTitle['confirmationEmail'] = $subjectTitle;
		if (preg_match ('/\{([^\} ]+)\}/', $subjectTitle, $matches)) {
			$element = $matches[1];
			if (isSet ($this->elements[$element]) && ($this->elements[$element]['type'] == 'input')) {
				$this->configureResultEmailedSubjectTitle['confirmationEmail'] = str_replace ('{' . $element . '}', $this->elements[$element]['data']['presented'], $subjectTitle);
			}
		}
	}
	
	
	/**
	 * Output the results to a CSV file
	 */
	function setOutputFile ($filename)
	{
		# Flag that this method is required
		$this->outputMethods['file'] = true;
		
		#!# Need to add a timestamp-writing option
		# If the file does not exist, check that its directory is writable
		if (!file_exists ($filename)) {
			$directory = dirname ($filename);
			if (!application::directoryIsWritable ($directory)) {
				$this->formSetupErrors['resultsFileNotCreatable'] = 'The specified results file cannot be created; please check the permissions for the containing directory.';
			}
			
		# If the file exists, check it is writable
		} else if (!is_writable ($filename)) {
			$this->formSetupErrors['resultsFileNotWritable'] = 'The specified (but already existing) results file is not writable; please check its permissions.';
		}
		
		# Assign the file location
		$this->configureResultFileFilename = $filename;
	}
	
	
	/**
	 * Output (display) the results to a database
	 */
	function setOutputDatabase ($dsn, $table = false)
	{
		# Flag that this method is required
		#!# Change to ->registerOutputMethod ($type) which then does the following line
		$this->outputMethods['database'] = true;
		
		# Set the DSN and table name
		$this->configureResultDatabaseDsn = $dsn;
		$this->configureResultDatabaseTable = ($table ? $table : $this->settings['name']);
	}
	
	
	
	/**
	 * Output (display) the results on screen
	 */
	function setOutputScreen ($displayUnsubmitted = true)
	{
		# Flag that this method is required
		$this->outputMethods['screen'] = true;
		
		# Flag whether to display as empty (rather than absent) those widgets which are optional and have had nothing submitted
		$this->configureResultScreenShowUnsubmitted = $displayUnsubmitted;
	}
	
	
	# Function to return the specification
	#!# This needs to exclude proxied widgets, e.g. password confirmation
	function getSpecification ()
	{
		# Return the elements array
		return $this->elements;
	}
	
	
	# Function to get database column specifications
	function getDatabaseColumnSpecification ($table = false)
	{
		# Loop through the elements and extract the specification
		$columns = array ();
		foreach ($this->elements as $name => $attributes) {
			if (isSet ($attributes['datatype'])) {
				$columns[$name] = $attributes['datatype'];
			}
		}
		
		# Return the result, with the key names in tact
		return $columns;
		
		/*
		# Create the SQL string
		$query = implode (",\n", $columns);
		
		# Add the table specification if necessary
		if ($table) {$query = "CREATE TABLE IF NOT EXISTS {$table} (" . "\n" . $query . "\n)";}
		
		# Return the assembled query
		return $query;
		*/
	}
	
	
	# Function to add built-in hidden security fields
	#!# This and hiddenSecurityFieldSubmissionInvalid () should be refactored into a small class
	function addHiddenSecurityFields ()
	{
		# Firstly (since username may be in use as a key) create a hidden username if required and a username is supplied
		$userCheckInUse = ($this->settings['user'] && $this->settings['userKey']);
		if ($userCheckInUse) {
			$securityFields['user'] = $this->settings['user'];
		}
		
		# Create a hidden timestamp if necessary
		if ($this->settings['timestamping']) {
			$securityFields['timestamp'] = $this->timestamp;
		}
		
		# Create a hidden IP field if necessary
		if ($this->settings['ipLogging']) {
			$securityFields['ip'] = $_SERVER['REMOTE_ADDR'];
		}
		
		# Make an internal call to the external interface
		#!# Add security-verifications as a reserved word
		if (isSet ($securityFields)) {
			$this->hidden (array (
			    'name'	=> 'security-verifications',
				'values'	=> $securityFields,
			));
		}
	}
	
	
	# Function to validate built-in hidden security fields
	function hiddenSecurityFieldSubmissionInvalid ()
	{
		# End checking if the form is not posted or there is no username
		if (!$this->formPosted || !$this->settings['user'] || !$this->settings['userKey']) {return false;}
		
		# Check for faked submissions
		if ($this->form['security-verifications']['user'] != $this->settings['user']) {
			$this->elementProblems = "\n" . '<p class="warning">The username which was silently submitted (' . $this->form['security-verifications']['user'] . ') does not match the username you previously logged in as (' . $this->settings['user'] . '). This has been reported as potential abuse and will be investigated.</p>';
			error_log ("A potentially fake submission has been made by {$this->settings['user']}, claiming to be {$this->form['security-verifications']['user']}. Please investigate.");
			#!# Should this really force ending of further checks?
			return true;
		}
		
		# If user uniqueness check is required, check that the user has not already made a submission
		if ($this->settings['loggedUserUnique']) {
			$csvData = application::getCsvData ($this->configureResultFileFilename);
			/* #!# Can't enable this section until application::getCsvData recognises the difference between an empty file and an unopenable/missing file
			if (!$csvData) {
				$this->formSetupErrors['csvInaccessible'] = 'It was not possible to make a check for repeat submissions as the data source could not be opened.';
				return true;
			} */
			if (array_key_exists ($this->settings['user'], $csvData)) {
				$this->html .= "\n" . '<p class="warning">You appear to have already made a submission. If you believe this is not the case, please contact the webmaster to resolve the situation.</p>';
				return true;
			}
		}
		
		# Otherwise return false (i.e. that there were no problems)
		return false;
	}
	
	
	/* Result viewing */
	
	# Function to assemble results into a chart
	function resultViewer ($suppliedArguments = array ())
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentDefaults = array (
			'ignoreFields' => array (),
			'ignoreHidden' => true,
			'showHeadings' => true,
			'heading' => 'h2',
			'anchors' => true,
			'tableClass' => 'lines',
			'tableChartClass' => 'lines surveyresultschart',
			'ulClass' => 'small compact',
			'ulIgnoreEmpty' => true,
			'showZeroNulls' => true,
			'showTableHeadings' => false,
			'showPercentages' => true,
			'piecharts' => true,
			'piechartStub' => '/images/piechart',
			'piechartWidth' => 250,
			'piechartHeight' => 200,
			'piechartDiv'	 => false,
			'chartPercentagePrecision' => 1,	// Number of decimal places to show for percentages in result charts
		);
		
		# Merge the arguments
		$arguments = application::assignArguments ($this->formSetupErrors, $suppliedArguments, $argumentDefaults, 'resultViewer');
		foreach ($arguments as $key => $value) {
			$$key = $value;
		}
		
		# Get the results (database storage; preferred to CSV if both in use)
		$dataSource = NULL;
		if ($this->dataBinding) {
			$data = $this->databaseConnection->select ($this->dataBinding['database'], $this->dataBinding['table']);
			$dataSource = 'database';
			
		# Get the results (CSV storage)
		} elseif ($this->configureResultFileFilename) {
			$data = application::getCsvData ($this->configureResultFileFilename, false, false, $keyAsFirstRow = true);
			$dataSource = 'csv';
			
		# End if no data source found
		} else {
			return $html  = "\n<p>No data source could be found.</p>";
		}
		
		# End if no data is available
		if (!$data) {
			return $html  = "\n<p>No submissions have so far been made.</p>";
		}
		
		# Ensure ignore fields is an array if supplied as a string (rather than a boolean or an array)
		if (is_string ($ignoreFields)) {$ignoreFields = application::ensureArray ($ignoreFields);}
		
		# Loop through the data and reverse the table direction (i.e. convert from per-row data to per-column data)
		$rawData = array ();
		foreach ($data as $submissionKey => $record) {
			foreach ($record as $key => $value) {
				$rawData[$key][$submissionKey] = $value;
			}
		}
		
		# If any elements end up with values split into different fields, adjust the results for that field into a hierarchy
		$fields = array ();
		$missingFields = array ();
		$results = array ();
		$nestedFields = array ();
		foreach ($this->elements as $field => $elementAttributes) {
			
			# Skip discarded fields
			if ($elementAttributes['discard']) {continue;}
			
			# Skip headings
			if ($elementAttributes['type'] == 'heading') {continue;}
			
			# Skip hidden fields if required
			if ($ignoreHidden && ($elementAttributes['type'] == 'hidden')) {continue;}
			
			# Checkbox fields require special handling
			if (($elementAttributes['type'] == 'checkboxes')) {
				if ($elementAttributes['values'] && is_array ($elementAttributes['values'])) {
					
					# Checkboxes stored as individual headings in a CSV
					foreach ($elementAttributes['values'] as $value => $visible) {
						$keyNameNestedType = "{$field}: {$value}";	// Emulation of $nestParent handling in application::arrayToCsv ()
						if (isSet ($rawData[$keyNameNestedType])) {
							$results[$field][$value] = $rawData[$keyNameNestedType];
							$nestedFields[$field] = true;
						}
					}
					
					# Otherwise deal with compiled, comma-separated SET lists, by looping through each selection list and break it down into values, to create a tally of the selected values, using the same data structure as above
					if (!isSet ($nestedFields[$field])) {
						foreach ($rawData[$field] as $index => $selectionGroupString) {
							$selections = explode (',', $selectionGroupString);	// Note that SET values cannot contain a comma so this is entirely safe
							foreach ($elementAttributes['values'] as $value => $visible) {
								$results[$field][$value][$index] = (in_array ($value, $selections) ? 1 : NULL);
							}
						}
					}
					
					# Move to the next field
					continue;
				}
			}
			
			# Skip if the field does not exist in the raw data
			if (!isSet ($rawData[$field])) {
				$missingFields[] = $field;
				continue;
			}
			
			# Add the raw data into the results as a normal field
			$results[$field] = $rawData[$field];
		}
		
		# Loop through the fields to compile their records into data
		$output = array ();
		$unknownValues = array ();
		$noResponse = '<span class="comment"><em>[No response]</em></span>';
		foreach ($this->elements as $field => $attributes) {
			
			# Show headings if necessary
			if (($attributes['type'] == 'heading') && $showHeadings) {
				$output[$field]['results'] = $attributes['html'];
				continue;
			}
			
			# Skip if the data is not in the results
			if (!isSet ($results[$field])) {continue;}
			
			# Skip this field if not required
			if ($ignoreFields) {
				if (is_array ($ignoreFields) && in_array ($field, $ignoreFields)) {continue;}
			}
			
			# Get the responses for this field
			$responses = $results[$field];
			
			# Create the heading
			$output[$field]['heading'] = (isSet ($this->elements[$field]['title']) ? $this->elements[$field]['title'] : '[No heading]');
			
			# State if no responses have been found for this field
			if (!$responses) {
				$output[$field]['results'] = "\n<p>No submissions for this question have so far been made.</p>";
				continue;
			}
			
			# Determine if this field is a chart type or a table chart type
			$isPieChartType = (isSet ($this->elements[$field]) && (($this->elements[$field]['type'] == 'radiobuttons') || ($this->elements[$field]['type'] == 'select')));
			$isTableChartType = ($attributes['type'] == 'checkboxes');
			
			# Render the chart types
			if ($isPieChartType) {
				
				# Count the number of instances of each responses; the NULL check is a workaround to avoid the "Can only count STRING and INTEGER values!" error from array_count_values
				#!# Other checks needed for e.g. BINARY values?
				foreach ($responses as $key => $value) {
					if (is_null ($value)) {
						$responses[$key] = '';
					}
				}
				$instances = array_count_values ($responses);
				
				# Determine the total responses
				$totalResponses = count ($responses);
				
				# If there are empty responses add a null response at the end of the values list
				$nullAvailable = false;
				if (!$this->elements[$field]['required']) {
					$nullAvailable = true;
					$this->elements[$field]['values'][''] = $noResponse;
				}
				
				# Check for values in the submissions that are not in the available values and compile a list of these
				if ($differences = array_diff (array_keys ($instances), array_keys ($this->elements[$field]['values']))) {
					foreach ($differences as $key => $value) {
						$unknownValues[] = "{$value} [in {$field}]";
					}
				}
				
				# Compile the table of responses
				$table = array ();
				$respondents = array ();
				$percentages = array ();
				foreach ($this->elements[$field]['values'] as $value => $visible) {
					
					# Determine the numeric number of respondents for this value
					$respondents[$value] = (array_key_exists ($value, $instances) ? $instances[$value] : 0);
					
					# If required, don't add the nulls to the  results table if there have been zero null instances
					if (!$showZeroNulls) {
						if ($nullAvailable && ($value == '') && !$respondents[$value]) {
							continue;
						}
					}
					
					# Create the main columns
					#!# This solution is a little bit hacky
					$table[$value][''] = ($visible == $noResponse ? $visible : htmlspecialchars ($visible));	// Heading would be 'Response'
					$table[$value]['Respondents'] = $respondents[$value];
					
					# Show percentages if required
					$percentages[$value] = round ((($respondents[$value] / $totalResponses) * 100), $chartPercentagePrecision);
					if ($showPercentages) {
						$table[$value]['Percentage'] = ($totalResponses ? $percentages[$value] . '%' : 'n/a');
					}
				}
				
				# Convert the table into HTML
				$output[$field]['results'] = application::htmlTable ($table, array (), $tableClass, $showKey = false, false, $allowHtml = true, false, false, false, array (), false, $showTableHeadings);
				
				# Add a piechart if wanted and wrap it in a div/table as required
				if ($piecharts) {
					
					# Find a suitable separator by checking a string made up of all the keys and values; by default , is used, but if that exists in any string, try others
					$string = '';
					foreach ($percentages as $key => $value) {
						$string .= $key . $value;
					}
					$ok = false;
					$separator = ',';
					$comma = ',';
					while (!$ok) {
						$separator .= $comma;	// Add on another comma
						if (!substr_count ($string, $separator)) {	// If neither key nor value has the separator, then choose it
							$ok = true;
							// Therefore this separator will be used
						}
					}
					$separatorQueryString = ($separator != $comma ? "separator={$separator}&amp;" : '');
					
					# Write the HTML
					if ($piechartDiv) {
						$output[$field]['results'] = "\n<div class=\"surveyresults\">\n\t<div class=\"surveyresultstable\">{$output[$field]['results']}\n\t</div>\n\t<div class=\"surveyresultspiechart\">\n\t\t<img width=\"{$piechartWidth}\" height=\"{$piechartHeight}\" src=\"{$piechartStub}?{$separatorQueryString}values=" . htmlspecialchars (implode ($separator, array_values ($percentages)) . '&desc=' . implode ($separator, array_keys ($percentages))) . "&amp;width={$piechartWidth}&amp;height={$piechartHeight}\" alt=\"Piechart of results\" />\n\t</div>\n</div>";
					} else {
						$output[$field]['results'] = "\n<table class=\"surveyresults\">\n\t<tr>\n\t\t<td class=\"surveyresultstable\">{$output[$field]['results']}</td>\n\t\t<td class=\"surveyresultspiechart\"><img width=\"{$piechartWidth}\" height=\"{$piechartHeight}\" src=\"{$piechartStub}?{$separatorQueryString}values=" . htmlspecialchars (implode ($separator, array_values ($percentages)) . '&desc=' . implode ($separator, array_keys ($percentages))) . "&amp;width={$piechartWidth}&amp;height={$piechartHeight}\" alt=\"Piechart of results\" /></td>\n\t</tr>\n</table>";
					}
				}
				
			# Render the table types
			} else if ($isTableChartType) {
				
				# Compile the results
				$table = array ();
				foreach ($this->elements[$field]['values'] as $value => $visible) {
					
					# Determine the numeric number of respondents for this value
					# Add the value
					$table[$value][''] = htmlspecialchars ($value);
					$table[$value]['respondents'] = (array_key_exists ($value, $responses) ? array_sum ($responses[$value]) : 0);
					
					# Show percentages if required
					$totalResponses = count ($responses[$value]);
					$percentages[$value] = round ((($table[$value]['respondents'] / $totalResponses) * 100), $chartPercentagePrecision);
					if ($showPercentages) {
						$table[$value]['percentage'] = $percentages[$value] . '%';
						$table[$value]['chart'] = "<div style=\"width: {$percentages[$value]}%\">{$percentages[$value]}%</div>";
					}
				}
				
				# Convert the data into an HTML table
				$output[$field]['results'] = application::htmlTable ($table, array (), $tableChartClass, $showKey = false, false, $allowHtml = true, false, $addCellClasses = true, false, array (), false, $showTableHeadings);
				
			# Render the list types
			} else {
				
				foreach ($responses as $index => $value) {
					$responses[$index] = nl2br (htmlspecialchars (trim ($value)));
				}
				$output[$field]['results'] = application::htmlUl ($responses, 1, $ulClass, $ulIgnoreEmpty);
			}
		}
		
		# Throw a setup error if expected fields are not in the CSV (if this happens, it indicates a programming error)
		if ($missingFields) {
			#!# Need to have ->specialchars applied to the fieldnames
			$this->formSetupErrors['resultReaderMissingFields'] = 'The following fields were not found in the result data: <strong>' . implode ('</strong>, <strong>', $missingFields) . '</strong>; please check the data source or consult the author of the webform system.';
		}
		
		# Throw a setup error if unknown values are found
		if ($unknownValues) {
			$this->formSetupErrors['resultReaderUnknownValues'] = 'The following unknown values were found in the result data: <strong>' . htmlspecialchars (implode ('</strong>, <strong>', $unknownValues)) . '</strong>.';
		}
		
		# End if there are form setup errors and report these
		if ($this->formSetupErrors) {
			$this->_setupOk ();
			echo $this->html;
			return false;
		}
		
		# Compile the HTML
		$html  = '';
		foreach ($output as $field => $results) {
			if (isSet ($results['heading'])) {
				$fieldEscaped = htmlspecialchars ($field);
				$html .= "\n\n<{$heading} id=\"{$fieldEscaped}\">" . ($anchors ? "<a href=\"#{$fieldEscaped}\">#</a> " : '') . htmlspecialchars ($results['heading']) . "</{$heading}>";
			}
			$html .= "\n" . $results['results'];
		}
		
		# Return the HTML
		return $html;
	}
	
	
	
	## Main processing ##
	
	
	# Function to return the submitted but pre-finalised data, for use in adding additional checks; effectively this provides a kind of callback facility
	public function getUnfinalisedData ()
	{
		# Return the form data, or an empty array (evaluating to false) if not posted
		return ($this->formPosted ? $this->getData () : array ());
	}
	
	
	# Function to extract the values from submitted data
	private function getData ()
	{
		# Get the presentation defaults
		$presentationDefaults = $this->presentationDefaults ($returnFullAvailabilityArray = false, $includeDescriptions = false);
		
		# Loop through each field and obtain the value
		$result = array ();
		foreach ($this->elements as $name => $element) {
			if (isSet ($element['data'])) {
				$widgetType = $element['type'];
				$defaultProcessingPresentationType = $presentationDefaults[$widgetType]['processing'];
				$result[$name] = $element['data'][$defaultProcessingPresentationType];
			}
		}
		
		# Return the data
		return $result;
	}
	
	
	/**
	 * Process/display the form (main wrapper function)
	 */
	function process (&$html = NULL)	// Note that &$value = defaultValue is not supported in PHP4 - see http://devzone.zend.com/node/view/id/1714#Heading5 point 3; if running PHP4, (a) remove the & and (b) change var $minimumPhpVersion above to 4.3
	{
		# Determine whether the HTML is shown directly
		$showHtmlDirectly = ($html === NULL);
		
		# Prepend the supplied HTML to the main HTML
		if ($html) {$this->html = $html . $this->html;}
		
		# Open the surrounding <div> if relevant
		#!# This should not be done if the form is successful
		$scaffoldHtml  = '';
		if ($this->settings['div']) {
			$scaffoldHtml .= "\n\n<div class=\"{$this->settings['div']}\">";
			$this->html .= $scaffoldHtml;
		}
		
		# Show the presentation matrix if required (this is allowed to bypass the form setup so that the administrator can see what corrections are needed)
		if ($this->settings['displayPresentationMatrix']) {$this->displayPresentationMatrix ();}
		
		# Check if the form and PHP environment has been set up OK
		if (!$this->_setupOk ()) {
			if ($this->settings['div']) {$this->html .= "\n</div>";}
			if ($showHtmlDirectly) {echo $this->html;}
			$html = $this->html;
			return false;
		}
		
		# Show debugging information firstly if required
		if ($this->settings['debug']) {$this->showDebuggingInformation ();}
		
		# Check whether the user is a valid user (must be before the setupOk check)
		if (!$this->validUser ()) {
			if ($this->settings['div']) {$this->html .= "\n</div>";}
			if ($showHtmlDirectly) {echo $this->html;}
			$html = $this->html;
			return false;
		}
		
		# Check whether the facility is open
		if (!$this->facilityIsOpen ()) {
			if ($this->settings['div']) {$this->html .= "\n</div>";}
			if ($showHtmlDirectly) {echo $this->html;}
			$html = $this->html;
			return false;
		}
		
		# Validate hidden security fields
		if ($this->hiddenSecurityFieldSubmissionInvalid ()) {
			if ($this->settings['div']) {$this->html .= "\n</div>";}
			if ($showHtmlDirectly) {echo $this->html;}
			$html = $this->html;
			return false;
		}
		
		# Perform replacement on the description at top-level if required
		if ($this->settings['titleReplacements']) {
			foreach ($this->elements as $name => $elementAttributes) {
				$this->elements[$name]['title'] = str_replace (array_keys ($this->settings['titleReplacements']), array_values ($this->settings['titleReplacements']), $elementAttributes['title']);
			}
		}
		
		# Determine if any kind of refresh button has been selected (either a __refresh or __refresh_<cleaned-id> expandable type)
		$formRefreshed = false;
		if (isSet ($this->collection['__refresh'])) {
			$formRefreshed = true;
		} else {
			foreach ($this->elements as $name => $elementAttributes) {
				$checkForRefreshWidgetName = '__refresh_' . $this->cleanId ($name);	// e.g. if a select widget called 'foo' has the 'expandable' attribute set, then check for __refresh_foo
				if (isSet ($this->collection[$checkForRefreshWidgetName])) {
					$formRefreshed = true;
					break;
				}
			}
		}
		
		# If the form is not posted or contains problems, display it and flag that it has been displayed
		$elementProblems = $this->getElementProblems ();
		if (!$this->formPosted || $elementProblems || $formRefreshed || ($this->settings['reappear'] && $this->formPosted && !$elementProblems)) {
			
			# Run the callback function if one is set
			if ($this->settings['callback']) {
				$this->settings['callback'] ($this->elementProblems ? -1 : 0);
			}
			
			# Add a note about refreshing
			if ($formRefreshed) {
				$this->html .= '<p><em>The form below has been refreshed but not yet submitted.</em></p>';
				$this->elementProblems = array ();	// Clear the element problems list in case this is being shown in templating mode
			}
			
			# Display the form and any problems then end
			$this->html .= $this->constructFormHtml ($this->elements, $this->elementProblems);
			if (!$this->formPosted || $elementProblems || $formRefreshed) {
				#!# This should not be done if the form is successful
				if ($this->settings['div']) {$this->html .= "\n</div>";}
				if ($showHtmlDirectly) {echo $this->html;}
				$html = $this->html;
				return false;
			}
		}
		
		# Process any form uploads
		$this->doUploads ();
		
		# Prepare the data
		$this->outputData = $this->prepareData ();
		
		# If required, display a summary confirmation of the result
		if ($this->settings['formCompleteText']) {$this->html .= "\n" . '<p class="completion">' . $this->settings['formCompleteText'] . ' </p>';}
		
		# Determine presentation format for each element
		$this->mergeInPresentationDefaults ();
		
		# Loop through each of the processing methods and output it based on the requested method
		foreach ($this->outputMethods as $outputType => $required) {
			$this->outputData ($outputType);
		}
		
		# If required, display a link to reset the page
		if ($this->settings['formCompleteText']) {$this->html .= "\n" . '<p><a href="' . $_SERVER['REQUEST_URI'] . '">Click here to reset the page.</a></p>';}
		
		# Close the surrounding <div> if relevant
		#!# This should not be done if the form is successful
		if ($this->settings['div']) {
			$scaffoldHtml .= "\n\n</div>";
			$this->html .= "\n\n</div>";
		}
		
		# If no HTML has been added, clear the surrounding div
		if ($this->html == $html . $scaffoldHtml) {
			$this->html = $html;
		}
		
		# Deal with the HTML
		if ($showHtmlDirectly) {echo $this->html;}
		$html = $this->html;
		// $html;	// Nothing is done with $html - it was passed by reference, if at all
		
		# Get the data
		$data = $this->outputData ('processing');
		
		# If the data is grouped, rearrange it into groups first
		if ($this->prefixedGroups) {
			foreach ($this->prefixedGroups as $group => $fields) {
				$thisGroupEmpty = true;	// Flag to detect all fields in the group being not completed
				foreach ($fields as $field) {
					#!# Currently this will NOT filter data which is in array format, e.g. a select field with the default output type
					if ($data[$field]) {$thisGroupEmpty = false;}
					$unprefixedFieldname = preg_replace ("/^{$group}_/", '', $field);
					$groupedData[$group][$unprefixedFieldname] = $data[$field];
					unset ($data[$field]);
				}
				
				# Omit this group of fields in the output if it is empty
				if ($this->settings['prefixedGroupsFilterEmpty']) {
					if ($thisGroupEmpty) {
						unset ($groupedData[$group]);
					}
				}
			}
			
			# Add on the remainder into a new group, called '0'
			if ($data) {
				$groupedData[0] = $data;
			}
			$data = $groupedData;
		}
		
		# Return the data (whether virgin or grouped)
		return $data;
	}
	
	
	## Form processing support ##
	
	# Function to determine whether this facility is open
	function facilityIsOpen ()
	{
		# Check that the opening time has passed, if one is specified, ensuring that the date is correctly specified
		if ($this->settings['opening']) {
			if (time () < strtotime ($this->settings['opening'] . ' GMT')) {
				$this->html .= '<p class="warning">This facility is not yet open. Please return later.</p>';
				return false;
			}
		}
		
		# Check that the closing time has passed
		if ($this->settings['closing']) {
			if (time () > strtotime ($this->settings['closing'] . ' GMT')) {
				$this->html .= '<p class="warning">This facility is now closed.</p>';
				return false;
			}
		}
		
		# Otherwise return true
		return true;
	}
	
	
	# Function to determine if the user is a valid user
	function validUser ()
	{
		# Return true if no users are specified
		if (!$this->settings['validUsers']) {return true;}
		
		# If '*' is specified for valid users, allow any through
		if ($this->settings['validUsers'][0] == '*') {return true;}
		
		# If the username is supplied in a list, return true
		if (in_array ($this->settings['user'], $this->settings['validUsers'])) {return true;}
		
		# Otherwise state that the user is not in the list and return false
		$this->html .= "\n" . '<p class="warning">You do not appear to be in the list of valid users. If you believe you should be, please contact the webmaster to resolve the situation.</p>';
		return false;
	}
	
	
	/**
	 * Function to check for form setup errors
	 * @todo Add all sorts of other form setup checks as flags within this function
	 * @access private
	 */
	function _setupOk ()
	{
		# Check the PHP environment set up is OK
		$this->validEnvironment ();
		
		# Check that there are no namespace clashes against internal defaults
		$this->preventNamespaceClashes ();
		
		# If a user is to be required, ensure there is a server-supplied username
		if ($this->settings['validUsers'] && !$this->settings['user']) {$this->formSetupErrors['usernameMissing'] = 'No username is being supplied, but the form setup requires that one is supplied, either explicitly or implicitly through the server environment. Please check the server configuration.';}
		
		# If a user uniqueness check is required, ensure that the file output mode is in use and that the user is being logged as a CSV key
		if ($this->settings['loggedUserUnique'] && !$this->outputMethods['file']) {$this->formSetupErrors['loggedUserUniqueRequiresFileOutput'] = "The settings specify that usernames are checked for uniqueness against existing submissions, but no log file of submissions is being made. Please ensure that the 'file' output type is enabled if wanting to check for uniqueness.";}
		if ($this->settings['loggedUserUnique'] && !$this->settings['userKey']) {$this->formSetupErrors['loggedUserUniqueRequiresUserKey'] = 'The settings specify that usernames are checked for uniqueness against existing submissions, but usernames are not set to be logged in the data. Please ensure that both are enabled if wanting to check for uniqueness.';}
		
		# Check that an empty form hasn't been requested (i.e. there must be at least one form field)
		#!# This needs to be modified to take account of headers (which should not be included)
		if (empty ($this->elements)) {$this->formSetupErrors['formEmpty'] = 'No form elements have been defined (i.e. the form is empty).';}
		
		# If there are any duplicated keys, list each duplicated key in bold with a comma between (but not after) each
		if ($this->duplicatedElementNames) {$this->formSetupErrors['duplicatedElementNames'] = 'The following field ' . (count (array_unique ($this->duplicatedElementNames)) == 1 ? 'name has' : 'names have been') . ' been duplicated in the form setup: <strong>' . implode ('</strong>, <strong>', array_unique ($this->duplicatedElementNames)) .  '</strong>.';}
		
		# Validate the output format syntax items, looping through each defined element that has an output configuration defined
		#!# Move this block into a new widget object's constructor
		$formatSyntaxInvalidElements = array ();
		$availableOutputFormats = $this->presentationDefaults ($returnFullAvailabilityArray = true, $includeDescriptions = false);
		foreach ($this->elements as $name => $elementAttributes) {
			if (!$elementAttributes['output']) {continue;}
			
			# Define the supported formats for this type of element
			$supportedFormats = $availableOutputFormats[$elementAttributes['type']];
			
			# Loop through each output type specified in the form setup
			foreach ($elementAttributes['output'] as $outputFormatType => $outputFormatValue) {
				
				# Check that the type and value are both supported
				if (!array_key_exists ($outputFormatType, $supportedFormats) || !in_array ($outputFormatValue, $supportedFormats[$outputFormatType])) {
					$formatSyntaxInvalidElements[$name] = true;
					break;
				}
			}
		}
		if ($formatSyntaxInvalidElements) {$this->formSetupErrors['outputFormatMismatch'] = 'The following field ' . (count ($formatSyntaxInvalidElements) == 1 ? 'name has' : 'names have') . " an incorrect 'output' setting: <strong>" . implode ('</strong>, <strong>', array_keys ($formatSyntaxInvalidElements)) .  '</strong>; the administrator should switch on the \'displayPresentationMatrix\' option in the settings to check the syntax.';}
		
		# Check templating in template mode
		$this->setupTemplating ();
		
		# Validate the callback mode setup
		if ($this->settings['callback'] && !function_exists ($this->settings['callback'])) {
			$this->formSetupErrors['callback'] = 'You specified a callback function but no such function exists.';
		}
		
		# Check group validation checks are valid
		$this->_checkGroupValidations ();
		
		# If there are any form setup errors - a combination of those just defined and those assigned earlier in the form processing, show them
		if (!empty ($this->formSetupErrors)) {
			$setupErrorText = application::showUserErrors ($this->formSetupErrors, $parentTabLevel = 1, (count ($this->formSetupErrors) > 1 ? 'Various errors were' : 'An error was') . " found in the setup of the form. The website's administrator needs to correct the configuration before the form will work:");
			$this->html .= $setupErrorText;
			
			# E-mail the errors to the admin if wanted
			foreach ($this->formSetupErrors as $error) {
				$errorTexts[] = "\n- " . strip_tags ($error);
			}
			if ($this->settings['mailAdminErrors']) {
				$administrator = (application::validEmail ($this->settings['mailAdminErrors']) ? $this->settings['mailAdminErrors'] : $_SERVER['SERVER_ADMIN']);
				application::utf8Mail (
					$administrator,
					'Form setup error',
					wordwrap ("The webform at \n" . $_SERVER['_PAGE_URL'] . "\nreports the following ultimateForm setup misconfiguration:\n\n" . implode ("\n", $errorTexts)),
					$additionalHeaders = 'From: Website feedback <' . $administrator . ">\r\n"
				);
			}
		}
		
		# Set that the form has effectively been displayed
		$this->formDisplayed = true;
		
		# Return true (i.e. form set up OK) if the errors array is empty
		return (empty ($this->formSetupErrors));
	}
	
	
	# Function to set up a database connection
	function _setupDatabaseConnection ()
	{
		# Nothing to do if no connection supplied
		if (!$this->settings['databaseConnection']) {return;}
		
		# Now that a database connection is confirmed required, set it to be false (rather than NULL) until overriden (this is important for later checking when using the connection)
		$this->databaseConnection = false;
		
		# If the link is not a database resource/object but is an array or a file use open that and end
		#!# Use of is_resource won't properly work yet
		if (/*is_resource ($this->settings['databaseConnection']) || */ is_object ($this->settings['databaseConnection'])) {
			$this->databaseConnection = $this->settings['databaseConnection'];
			return true;
		}
		
		# If it's an array type, assign the array directly
		if (is_array ($this->settings['databaseConnection'])) {
			$credentials = $this->settings['databaseConnection'];
			
		# If it's a file, open it and ensure there is a $credentials array given
		} elseif (is_file ($this->settings['databaseConnection'])) {
			if (!include ($this->settings['databaseConnection'])) {
				$this->formSetupErrors['databaseCredentialsFileNotFound'] ('The database credentials file could not be open or does not exist.');
				return false;
			}
			if (!isSet ($credentials)) {
				$this->formSetupErrors['databaseCredentialsFileNoArray'] ('The database credentials file did not contain a $credentials array.');
				return false;
			}
			
		# If it's none of the above, throw an error
		} else {
			$this->formSetupErrors['databaseCredentialsUnsupported'] = 'The database credentials setting does not seem to be a supported type or is otherwise invalid.';
			return false;
		}
		
		# Create the connection using the credentials array now assigned
		require_once ('database.php');
		$this->databaseConnection = new database ($credentials['hostname'], $credentials['username'], $credentials['password']);
		if (!$this->databaseConnection->connection) {
			$this->formSetupErrors['databaseCredentialsFile'] = 'The database connection failed for some reason.';
			return false;
		}
	}
	
	
	# Function to check templating
	function setupTemplating ()
	{
		# End further checks if not in the display mode
		if ($this->settings['display'] != 'template') {return;}
		
		# Ensure the template pattern includes the placemarker %element
		$placemarker = '%element';
		$checkParameters = array ('displayTemplatePatternWidget', 'displayTemplatePatternLabel', 'displayTemplatePatternSpecial');
		foreach ($checkParameters as $checkParameter) {
			if (strpos ($this->settings[$checkParameter], $placemarker) === false) {
				$this->formSetupErrors["{$checkParameter}Invalid"] = "The <tt>{$checkParameter}</tt> parameter must include the placemarker <tt>{$placemarker}</tt> ; by default the parameter's value is <tt>{$this->argumentDefaults[$checkParameter]}</tt>";
			}
		}
		
		# Check that none of the $checkParameters items are the same
		foreach ($checkParameters as $checkParameter) {
			$values[] = $this->settings[$checkParameter];
		}
		if (count ($values) != count (array_unique ($values))) {
			$this->formSetupErrors['displayTemplatePatternDuplication'] = 'The values of the parameters <tt>' . implode ('</tt>, <tt>', $checkParameters) . '</tt> must all be unique.';
		}
		
		# Determine if the template is a file or string
		if (is_file ($this->settings['displayTemplate'])) {
			
			# Check that the template is readable
			if (!is_readable ($this->settings['displayTemplate'])) {
				$this->formSetupErrors['templateNotFound'] = 'You appear to have specified a template file for the <tt>displayTemplate</tt> parameter, but the file could not be opened.</tt>';
				return false;
			}
			$this->displayTemplateContents = file_get_contents ($this->settings['displayTemplate']);
		} else {
			$this->displayTemplateContents = $this->settings['displayTemplate'];
		}
		
		# Assemble the list of elements and their replacements
		$elements = array_keys ($this->elements);
		$this->displayTemplateElementReplacements = array ();
		foreach ($elements as $element) {
			$this->displayTemplateElementReplacements[$element]['widget'] = str_replace ($placemarker, $element, $this->settings['displayTemplatePatternWidget']);
			$this->displayTemplateElementReplacements[$element]['label'] = str_replace ($placemarker, $element, $this->settings['displayTemplatePatternLabel']);
		}
		
		# Parse the template to ensure that all non-hidden elements exist in the template
		$missingElements = array ();
		foreach ($this->displayTemplateElementReplacements as $element => $replacements) {
			if ($this->elements[$element]['type'] == 'hidden') {continue;}
			if (substr_count ($this->displayTemplateContents, $replacements['widget']) !== 1) {
				$missingElements[] = $replacements['widget'];
			}
		}
		
		# Construct an array of missing elements if there are any; labels are considered optional
		if ($missingElements) {
			$this->formSetupErrors['templateElementsNotFoundWidget'] = 'The following element ' . ((count ($missingElements) == 1) ? 'string was' : 'strings were') . ' not present once only in the template you specified: ' . implode (', ', $missingElements);
		}
		
		# Define special placemarker names and whether they are required; these can appear more than once
		$specials = array (
			'PROBLEMS' => true,				// Placemarker for the element problems box
			'SUBMIT' => true,				// Placemarker for the submit button
			'RESET' => $this->settings['resetButton'],	// Placemarker for the reset button - if there is one
			'REQUIRED' => false,			// Placemarker for the required fields indicator text
		);
		if ($this->settings['refreshButton']) {
			$specials['REFRESH'] = false;	// Placemarker for a refresh button
		}
		
		# Loop through each special, allocating its replacement shortcut and checking it exists if necessary
		$missingElements = array ();
		foreach ($specials as $special => $required) {
			$this->displayTemplateElementReplacementsSpecials[$special] = str_replace ($placemarker, $special, $this->settings['displayTemplatePatternSpecial']);
			if ($required) {
				if (!substr_count ($this->displayTemplateContents, $this->displayTemplateElementReplacementsSpecials[$special])) {
					$missingElements[] = $this->displayTemplateElementReplacementsSpecials[$special];
				}
			}
		}
		
		# Construct an array of missing elements if there are any; labels are considered optional
		if ($missingElements) {
			$this->formSetupErrors['templateElementsNotFoundSpecials'] = 'The following element ' . ((count ($missingElements) == 1) ? 'string was' : 'strings were') . ' not present at least once in the template you specified: ' . implode (', ', $missingElements);
		}
	}
	
	
	/**
	 * Function to perform validity checks to ensure a correct PHP environment
	 * @access private
	 */
	function validEnvironment ()
	{
		# Check the minimum PHP version, to ensure that all required functions will be available
		if (version_compare (PHP_VERSION, $this->minimumPhpVersion, '<')) {$this->formSetupErrors['environmentPhpVersion'] = 'The server must be running PHP version <strong>' . $this->minimumPhpVersion . '</strong> or higher.';}
		
		# Check that global user variables cannot be imported into the program
		if ((bool) ini_get ('register_globals')) {$this->formSetupErrors['environmentRegisterGlobals'] = 'The PHP configuration setting register_globals must be set to <strong>off</strong>.';}
		
		# Check that magic_quotes are switched off; escaping of user input is handled manually
		#!# Replace these with data cleaning methods
		if ((bool) ini_get ('magic_quotes_gpc')) {$this->formSetupErrors['environmentMagicQuotesGpc'] = 'The PHP configuration setting magic_quotes_gpc must be set to <strong>off</strong>.';}
		if ((bool) ini_get ('magic_quotes_sybase')) {$this->formSetupErrors['environmentMagicQuotesSybase'] = 'The PHP configuration setting magic_quotes_sybase must be set to <strong>off</strong>.';}
		
		# Perform checks on upload-related settings if any elements are upload types and the check has not been run
		if ($this->uploadProperties) {
			
			# Ensure file uploads are allowed
			if (!ini_get ('file_uploads')) {
				$this->formSetupErrors['environmentFileUploads'] = 'The PHP configuration setting file_uploads must be set to <strong>on</strong> given that the form includes an upload element.';
			} else {
				
				# If file uploads are being allowed, check that upload_max_filesize and post_max_size are valid
				if ((!preg_match ('/^(\d+)([bkm]*)$/iD', ini_get ('upload_max_filesize'))) || (!preg_match ('/^(\d+)([bkm]*)$/iD', ini_get ('post_max_size')))) {
					$this->formSetupErrors['environmentFileUploads'] = 'The PHP configuration setting upload_max_filesize/post_max_size must both be valid.';
				} else {
					
					# Given that file uploads are being allowed and the ensure that the upload size is not greater than the maximum POST size
					if (application::convertSizeToBytes (ini_get ('upload_max_filesize')) > application::convertSizeToBytes (ini_get ('post_max_size'))) {$this->formSetupErrors['environmentFileUploads'] = 'The PHP configuration setting upload_max_filesize cannot be greater than post_max_filesize; the form includes an upload element, so this misconfiguration must be corrected.';}
				}
			}
		}
	}
	
	
	# Function to register element names
	function registerElementName ($name)
	{
		# Add the name to the list of duplicated element names if it is already set
		if (isSet ($this->elements[$name])) {$this->duplicatedElementNames[] = $name;}
	}
	
	
	/**
	 * Function to check for namespace clashes against internal defaults
	 * @access private
	 */
	#!# Ideally replace each clashable item with an encoding method somehow or ideally eradicate the restrictions
	function preventNamespaceClashes ()
	{
		# Disallow [ or ] in a form name
		if ((strpos ($this->settings['name'], '[') !== false) || (strpos ($this->settings['name'], ']') !== false)) {
			$this->formSetupErrors['namespaceFormNameContainsSquareBrackets'] = 'The name of the form ('. $this->settings['name'] . ') cannot include square brackets.';
		}
		
		# Disallow valid e-mail addresses as an element name, to prevent setOutputEmail () picking a form element which should actually be an e-mail address
		foreach ($this->elements as $name => $elementAttributes) {
			if (application::validEmail ($name)) {
				$this->formSetupErrors['namespaceelementNameStartDisallowed'] = 'Element names cannot be in the format of an e-mail address.';
				break;
			}
		}
		
		# Disallow _heading at the start of an element
		#!# This will also be listed alongside the 'Element names cannot start with _heading'.. warning
		foreach ($this->elements as $name => $elementAttributes) {
			if (preg_match ('/^_heading/', $name)) {
				if ($elementAttributes['type'] != 'heading') {
					$disallowedelementNames[] = $name;
				}
			}
		}
		if (isSet ($disallowedelementNames)) {
			$this->formSetupErrors['namespaceelementNameStartDisallowed'] = 'Element names cannot start with _heading; the <strong>' . implode ('</strong>, <strong>', $disallowedelementNames) . '</strong> elements must therefore be renamed.';
		}
	}
	
	
	/**
	 * Function actually to display the form
	 * @access private
	 */
	function constructFormHtml ($elements, $problems)
	{
		# Define various HTML snippets
		$requiredFieldIndicatorHtml = "\n" . '<p class="requiredmessage"><strong>*</strong> Items marked with an asterisk [*] are required fields and must be fully completed.</p>';
		
		# Add the problems list
		if ($this->settings['display'] == 'template') {
			$html = '';
			$this->displayTemplateContents = str_replace ($this->displayTemplateElementReplacementsSpecials['PROBLEMS'], $this->problemsList ($problems), $this->displayTemplateContents);
		} else {
			$html  = "\n" . $this->problemsList ($problems);
		}
		
		# Add the required field indicator display message if required
		if (($this->settings['display'] != 'template') && ($this->settings['requiredFieldIndicator'] === 'top')) {$html .= $requiredFieldIndicatorHtml;}
		
		# Start the constructed form HTML
		$html .= "\n" . '<form method="' . $this->method . '" name="' . ($this->settings['name'] ? $this->settings['name'] : 'form') . '" action="' . $this->settings['submitTo'] . '" enctype="' . ($this->uploadProperties ? 'multipart/form-data' : 'application/x-www-form-urlencoded') . '" accept-charset="UTF-8">';
		
		# Start the HTML
		$formHtml = '';
		$hiddenHtml = "\n";
		
		# Determine whether to display the descriptions - display if on and any exist
		$displayDescriptions = false;
		if ($this->settings['displayDescriptions']) {
			foreach ($elements as $name => $elementAttributes) {
				if (!empty ($elementAttributes['description'])) {
					$displayDescriptions = true;
					break;
				}
			}
		}
		
		# Loop through each of the elements to construct the form HTML
		foreach ($elements as $name => $elementAttributes) {
			
			# For hidden elements, buffer the hidden HTML then skip remainder of loop execution; for the template type, remove the placemarker also
			if ($elementAttributes['type'] == 'hidden') {
				$hiddenHtml .= $elementAttributes['html'];
				/*
				# Remove any extraneous {hidden} indicators
				if ($this->settings['display'] == 'template') {
					$this->displayTemplateContents = str_replace ($this->displayTemplateElementReplacements[$name], '', $this->displayTemplateContents);
					$formHtml = $this->displayTemplateContents;
				}
				*/
				continue;
			}
			
			# Special case (to be eradicated - 'hidden visible' fields due to _hidden in dataBinding)
			if (array_key_exists ('_visible--DONOTUSETHISFLAGEXTERNALLY', $elementAttributes)) {
				$hiddenHtml .= $elementAttributes['_visible--DONOTUSETHISFLAGEXTERNALLY'];
				continue;
			}
			
			
			# If colons are set to show, add them
			if ($this->settings['displayColons']) {$elementAttributes['title'] .= ':';}
			
			# If the element is required, and indicators are in use add an indicator
			$elementIsRequired = ($this->settings['requiredFieldIndicator'] && $elementAttributes['required']);
			if ($elementIsRequired) {
				$elementAttributes['title'] .= '&nbsp;*';
			}
			
			# If the form has been posted AND the element has any problems or is empty, add the warning CSS class
			if ($this->formPosted && (($elementAttributes['problems']) || ($elementAttributes['requiredButEmpty']) || (($elementAttributes['type'] == 'upload') && (isSet ($this->elementProblems['generic']) && isSet ($this->elementProblems['generic']['reselectUploads']))))) {
				$elementAttributes['title'] = '<span class="warning">' . $elementAttributes['title'] . '</span>';
			}
			
			# Select whether to show restriction guidelines
			$displayRestriction = ($this->settings['displayRestrictions'] && $elementAttributes['restriction']);
			
			# Clean the ID
			#!# Move this into the element attributes set at a per-element level, for consistency so that <label> is correct
			$id = $this->cleanId ($name);
			
			# Display the display text (in the required format), unless it's a hidden array (i.e. no elementText to appear)
			switch ($this->settings['display']) {
				
				# Display as paragraphs
				case 'paragraphs':
					if ($elementAttributes['type'] == 'heading') {
						$formHtml .= "\n" . $elementAttributes['html'];
					} else {
						$formHtml .= "\n" . '<p class="row ' . $id . ($this->settings['classShowType'] ? " {$elementAttributes['type']}" : '') . ($elementIsRequired ? " {$this->settings['requiredFieldClass']}" : '') . '"' . '>';
						$formHtml .= "\n\t";
						if ($this->settings['displayTitles']) {
							$formHtml .= $elementAttributes['title'] . '<br />';
							if ($displayRestriction) {$formHtml .= "<br /><span class=\"restriction\">(" . preg_replace ("/\n/", '<br />', $elementAttributes['restriction']) . ')</span>';}
						}
						$formHtml .= $elementAttributes['html'];
						#!# Need to have looped through each $elementAttributes['description'] and remove that column if there are no descriptions at all
						if ($displayDescriptions) {if ($elementAttributes['description']) {$formHtml .= "<br />\n<span class=\"description\">" . $elementAttributes['description'] . '</span>';}}
						$formHtml .= "\n</p>";
					}
					break;
					
				# Display using divs for CSS layout mode; this is different to paragraphs as the form fields are not conceptually paragraphs
				case 'css':
					$formHtml .= "\n" . '<div class="row ' . $id . ($this->settings['classShowType'] ? " {$elementAttributes['type']}" : '') . ($elementIsRequired ? " {$this->settings['requiredFieldClass']}" : '') . '" id="' . $id . '">';
					if ($elementAttributes['type'] == 'heading') {
						$formHtml .= "\n\t<span class=\"title\">" . $elementAttributes['html'] . '</span>';
					} else {
						$formHtml .= "\n\t";
						if ($this->settings['displayTitles']) {
							if ($displayRestriction) {
								$formHtml .= "<span class=\"label\">";
								$formHtml .= "\n\t\t" . $elementAttributes['title'];
								$formHtml .= "\n\t\t<span class=\"restriction\">(" . preg_replace ("/\n/", '<br />', $elementAttributes['restriction']) . ')</span>';
								$formHtml .= "\n\t</span>";
							} else {
								$formHtml .= "<span class=\"label\">" . $elementAttributes['title'] . '</span>';
							}
						}
						$formHtml .= "\n\t<span class=\"data\">" . $elementAttributes['html'] . '</span>';
						if ($displayDescriptions) {if ($elementAttributes['description']) {$formHtml .= "\n\t<span class=\"description\">" . $elementAttributes['description'] . '</span>';}}
					}
						$formHtml .= "\n</div>";
					break;
					
				# Templating - perform each replacement on a per-element basis
				case 'template':
					$this->displayTemplateContents = str_replace ($this->displayTemplateElementReplacements[$name], array ($elementAttributes['html'], $elementAttributes['title']), $this->displayTemplateContents);
					$formHtml = $this->displayTemplateContents;
					break;
				
				# Tables
				case 'tables':
				default:
					$formHtml .= "\n\t" . '<tr class="' . $id . ($this->settings['classShowType'] ? " {$elementAttributes['type']}" : '') . ($elementIsRequired ? " {$this->settings['requiredFieldClass']}" : '') . '">';
					if ($elementAttributes['type'] == 'heading') {
						# Start by determining the number of columns which will be needed for headings involving a colspan
						$colspan = 1 + ($this->settings['displayTitles']) + ($displayDescriptions);
						$formHtml .= "\n\t\t<td colspan=\"$colspan\">" . $elementAttributes['html'] . '</td>';
					} else {
						$formHtml .= "\n\t\t";
						if ($this->settings['displayTitles']) {
							$formHtml .= "<td class=\"title\">" . ($elementAttributes['title'] == '' ? '&nbsp;' : $elementAttributes['title']);
							if ($displayRestriction) {$formHtml .= "<br />\n\t\t\t<span class=\"restriction\">(" . preg_replace ("/\n/", '<br />', $elementAttributes['restriction']) . ")</span>\n\t\t";}
							$formHtml .= '</td>';
						}
						$formHtml .= "\n\t\t<td class=\"data\">" . $elementAttributes['html'] . '</td>';
						if ($displayDescriptions) {$formHtml .= "\n\t\t<td class=\"description\">" . ($elementAttributes['description'] == '' ? '&nbsp;' : $elementAttributes['description']) . '</td>';}
					}
					$formHtml .= "\n\t</tr>";
			}
		}
		
		# In the table mode, having compiled all the elements surround the elements with the table tag
		if ($this->settings['display'] == 'tables') {$formHtml = "\n\n" . '<table summary="Online submission form">' . $formHtml . "\n</table>";}
		
		# Add in any hidden HTML, between the </table> and </form> tags (this also works for the template, where it is stuck on afterwards
		$formHtml .= $hiddenHtml;
		
		# Add the form button, either at the start or end as required
		#!# submit_x and submit_y should be treated as a reserved word when using submitButtonAccesskey (i.e. generating type="image")
		$submitButtonText = $this->settings['submitButtonText'] . (!empty ($this->settings['submitButtonAccesskey']) ? '&nbsp; &nbsp;[Shift+Alt+' . $this->settings['submitButtonAccesskey'] . ']' : '');
		$formButtonHtml = '<input type="' . (!$this->settings['submitButtonImage'] ? 'submit' : "image\" src=\"{$this->settings['submitButtonImage']}\" name=\"submit\" alt=\"{$submitButtonText}") . '" value="' . $submitButtonText . '"' . (!empty ($this->settings['submitButtonAccesskey']) ? " accesskey=\"{$this->settings['submitButtonAccesskey']}\""  : '') . (is_numeric ($this->settings['submitButtonTabindex']) ? " tabindex=\"{$this->settings['submitButtonTabindex']}\"" : '') . ' class="button" />';
		if ($this->settings['refreshButton']) {
			$refreshButtonText = $this->settings['refreshButtonText'] . (!empty ($this->settings['refreshButtonAccesskey']) ? '&nbsp; &nbsp;[Shift+Alt+' . $this->settings['refreshButtonAccesskey'] . ']' : '');
			#!# Need to deny __refresh as a reserved form name
			$refreshButtonHtml = '<input name="__refresh" type="' . (!$this->settings['refreshButtonImage'] ? 'submit' : "image\" src=\"{$this->settings['refreshButtonImage']}\" name=\"submit\" alt=\"{$refreshButtonText}") . '" value="' . $refreshButtonText . '"' . (!empty ($this->settings['refreshButtonAccesskey']) ? " accesskey=\"{$this->settings['refreshButtonAccesskey']}\""  : '') . (is_numeric ($this->settings['refreshButtonTabindex']) ? " tabindex=\"{$this->settings['refreshButtonTabindex']}\"" : '') . ' class="button" />';
		}
		if ($this->settings['display'] == 'template') {
			$formHtml = str_replace ($this->displayTemplateElementReplacementsSpecials['SUBMIT'], $formButtonHtml, $formHtml);
			if ($this->settings['refreshButton']) {
				$formHtml = str_replace ($this->displayTemplateElementReplacementsSpecials['REFRESH'], $refreshButtonHtml, $formHtml);
			}
		} else {
			$formButtonHtml = "\n\n" . '<p class="submit">' . $formButtonHtml . '</p>';
			if ($this->settings['refreshButton']) {
				$refreshButtonHtml = "\n\n" . '<p class="refresh">' . $refreshButtonHtml . '</p>';
			}
			switch ($this->settings['submitButtonPosition']) {
				case 'start':
					$formHtml = $formButtonHtml . $formHtml;
					break;
				case 'both':
					$formHtml = $formButtonHtml . $formHtml . $formButtonHtml;
					break;
				case 'end':	// Fall-through
				default:
					$formHtml = $formHtml . $formButtonHtml;
			}
			if ($this->settings['refreshButton']) {
				$formHtml = ((!$this->settings['refreshButtonAtEnd']) ? ($refreshButtonHtml . $formHtml) : ($formHtml . $refreshButtonHtml));
			}
		}
		
		# Add in the required field indicator for the template version
		if (($this->settings['display'] == 'template') && ($this->settings['requiredFieldIndicator'])) {
			$formHtml = str_replace ($this->displayTemplateElementReplacementsSpecials['REQUIRED'], $requiredFieldIndicatorHtml, $formHtml);
		}
		
		# Add in a reset button if wanted
		if ($this->settings['resetButton']) {
			$resetButtonHtml = '<input value="' . $this->settings['resetButtonText'] . (!empty ($this->settings['resetButtonAccesskey']) ? '&nbsp; &nbsp;[Shift+Alt+' . $this->settings['resetButtonAccesskey'] . ']" accesskey="' . $this->settings['resetButtonAccesskey'] : '') . '" type="reset" class="resetbutton"' . (is_numeric ($this->settings['resetButtonTabindex']) ? " tabindex=\"{$this->settings['resetButtonTabindex']}\"" : '') . ' />';
			if ($this->settings['display'] == 'template') {
				$formHtml = str_replace ($this->displayTemplateElementReplacementsSpecials['RESET'], $resetButtonHtml, $formHtml);
			} else {
				$formHtml .= "\n" . '<p class="reset">' . $resetButtonHtml . '</p>';
			}
		}
		
		# Add in the form HTML
		$html .= $formHtml;
		
		# Continue the HTML
		$html .= "\n\n" . '</form>';
		
		# Add the required field indicator display message if required
		if (($this->settings['display'] != 'template') && ($this->settings['requiredFieldIndicator'] === 'bottom') || ($this->settings['requiredFieldIndicator'] === true)) {$html .= $requiredFieldIndicatorHtml;}
		
		# Return the HTML
		return $html;
	}
	
	
	/**
	 * Function to prepare a problems list
	 * @access private
	 */
	#!# Make these types generic rather than hard-coded
	function problemsList ($problems)
	{
		# Flatten the multi-level array of problems, starting first with the generic, top-level problems if any exist
		$problemsList = array ();
		if (isSet ($problems['generic'])) {
			foreach ($problems['generic'] as $name => $genericProblem) {
				$problemsList[] = $genericProblem;
			}
		}
		
		# Next, flatten the element-based problems, if any exist, starting with looping through each of the problems
		if (isSet ($problems['elements'])) {
			foreach ($problems['elements'] as $name => $elementProblems) {
				
				# Start an array of flattened element problems
				$currentElementProblemsList = array ();
				
				# Add each problem to the flattened array
				foreach ($elementProblems as $problemKey => $problemText) {
					$currentElementProblemsList[] = $problemText;
				}
				
				# If an item contains two or more errors, compile them and prefix them with introductory text
				$totalElementProblems = count ($elementProblems);
				$introductoryText = 'In the <strong>' . ($this->elements[$name]['title'] != '' ? $this->elements[$name]['title'] : ucfirst ($name)) . '</strong> section, ' . (($totalElementProblems > 1) ? "$totalElementProblems problems were" : 'a problem was') . ' found:';
				if ($totalElementProblems > 1) {
					$problemsList[] = application::showUserErrors ($currentElementProblemsList, $parentTabLevel = 2, $introductoryText, $nested = true);
				} else {
					
					# If there's just a single error for this element, carry the item through
					#!# Need to lcfirst the $problemtext here
					$problemsList[] = $introductoryText . ' ' . $problemText;
				}
			}
		}
		
		# Next the group if any exist
		if (isSet ($problems['group'])) {
			foreach ($problems['group'] as $name => $groupProblem) {
				$problemsList[] = $groupProblem;
			}
		}
		
		# Next the external problems if any exist
		if (isSet ($problems['external'])) {
			foreach ($problems['external'] as $name => $groupProblem) {
				$problemsList[] = $groupProblem;
			}
		}
		
		# Return a constructed list of problems (or empty string)
		return $html = (($this->formPosted && $problemsList) ? application::showUserErrors ($problemsList, $parentTabLevel = 0, ($this->settings['warningMessage'] ? $this->settings['warningMessage'] : (count ($problemsList) > 1 ? 'Various problems were' : 'A problem was') . ' found with the form information you submitted, as detailed below; please make the necessary corrections and re-submit the form:')) : '');
	}
	
	
	/**
	 * Function to prepare completed form data; the data is assembled into a compiled version (e.g. in the case of checkboxes, separated by commas) and a component version (which is an array); in the case of scalars, the component version is set to be the same as the compiled version
	 * @access private
	 */
	function prepareData ()
	{
		# Loop through each element, whether submitted or not (otherwise gaps may be left, e.g. in the CSV writing)
		foreach ($this->elements as $name => $elementAttributes) {
			
			# Discard if required; note that all the processing will have been done on each element; this is useful for e.g. a Captcha, where the submitted data merely needs to validate but is not used
			if ($elementAttributes['discard']) {continue;}
			
			# Add submitted items
			if ($this->elements[$name]['data']) {
				$outputData[$name] = $this->elements[$name]['data'];
			}
		}
		
		# Return the data
		return $outputData;
	}
	
	
	/**
	 * Function to check for problems
	 * @access private
	 */
	#!# The whole problems area needs refactoring
	function getElementProblems ()
	{
		# If the form is not posted, end here
		if (!$this->formPosted) {return false;}
		
		# Loop through each created form element (irrespective of whether it has been submitted or not), run checks for problems, and deal with multi-dimensional arrays
		foreach ($this->elements as $name => $elementAttributes) {
			
			# Check for specific problems which have been assigned in the per-element checks
			if ($this->elements[$name]['problems']) {
				
				# Assign the problem to the list of problems
				$this->elementProblems['elements'][$name] = $this->elements[$name]['problems'];
			}
			
			# Construct a list of required but incomplete fields
			if ($this->elements[$name]['requiredButEmpty']) {
				$incompleteFields[] = ($this->elements[$name]['title'] != '' ? $this->elements[$name]['title'] : ucfirst ($name));
			}
			
			#!# Do checks on hidden fields
		}
		
		# If there are any incomplete fields, add it to the start of the problems array
		if (!isSet ($this->elementProblems['generic'])) {$this->elementProblems['generic'] = array ();}
		if (isSet ($incompleteFields)) {
			$this->elementProblems['generic']['incompleteFields'] = "You need to enter a value for the following required " . ((count ($incompleteFields) == 1) ? 'field' : 'fields') . ': <strong>' . implode ('</strong>, <strong>', $incompleteFields) . '</strong>.';
		}
		
		# Run checks for multiple validation fields
		$this->elementProblems['group'] = $this->_groupValidationChecks ();
		
		# Add in externally-supplied problems (where the calling application has inserted data checked against ->getUnfinalisedData), which by default is an empty array
		$this->elementProblems['external'] = $this->externalProblems;
		
		# If there are no fields incomplete, remove the requirement to force upload(s) reselection
		$genericProblemsOtherThanUpload = ((count ($this->elementProblems['generic']) > 1) || ($this->elementProblems['generic'] && !isSet ($this->elementProblems['generic']['reselectUploads'])));
		#!# Make $this->elementProblems['elements'] always exist to remove this inconsistency
		if (!$genericProblemsOtherThanUpload && !isSet ($this->elementProblems['elements']) && !$this->elementProblems['group'] && !$this->elementProblems['external']) {
			if (isSet ($this->elementProblems['generic']['reselectUploads'])) {
				unset ($this->elementProblems['generic']['reselectUploads']);
			}
		}
		
		# Return a boolean of whether problems have been found or not
		#!# This needs to be made more generic, by looping through the first-level arrays to see if any second-level items exist; then new types of problems need not be registered here
		#!# Again, make $this->elementProblems['elements'] always exist to remove the isSet/!empty inconsistency
		return $problemsFound = (!empty ($this->elementProblems['generic'])) || (isSet ($this->elementProblems['elements']) || (!empty ($this->elementProblems['group'])) || (!empty ($this->elementProblems['external'])));
	}
	
	
	# Function to register a group validation check
	function validation ($type, $fields, $parameter = false)
	{
		# Register the (now validated) validation rule
		$this->validationRules[] = array ('type' => $type, 'fields' => $fields, 'parameter' => $parameter);
	}
	
	
	# Function to check the group validations are syntactically correct
	function _checkGroupValidations ()
	{
		# End if no rules
		if (!$this->validationRules) {return;}
		
		# Define the supported validation types and the error message (including a placeholder) which should appear if the check fails
		$this->validationTypes = array (
			'different' => 'The values for each of the sections %fields must be unique.',
			'same'		=> 'The values for each of the sections %fields must be the same.',
			'either'	=> 'One of the sections %fields must be completed.',
			'all'		=> 'The values for all of the sections %fields must be completed if one of them is.',
			'master'	=> 'The value for the field %fields must be completed if any of the other %parameter fields are completed.',
			'total'		=> 'In the sections %fields, the total number of items selected must be exactly %parameter.',
		);
		
		# Loop through each registered rule to check for setup problems (but do not perform the validations themselves)
		foreach ($this->validationRules as $validationRule) {
			
			# Ensure the validation is a valid type
			if (!array_key_exists ($validationRule['type'], $this->validationTypes)) {
//				$this->formSetupErrors['validationTypeInvalid'] = "The group validation type '<strong>{$validationRule['type']}</strong>' is not a supported type.";
				return;
			}
			
			# Ensure the fields are an array and that there are at least two
			if (!is_array ($validationRule['fields']) || (is_array ($validationRule['fields']) && (count ($validationRule['fields']) < 2))) {
				$this->formSetupErrors['validationFieldsInvalid'] = 'An array of at least two fields must be specified for a group validation rule.';
				return;
			}
			
			# Ensure the specified fields exist
			if ($missing = array_diff ($validationRule['fields'], array_keys ($this->elements))) {
				$this->formSetupErrors['validationFieldsAbsent'] = 'The field ' . (count ($missing) > 1 ? 'names' : 'name') . " '<strong>" . implode ("</strong>', '<strong>", $missing) . "</strong>' " . (count ($missing) > 1 ? 'names' : 'was') . " specified for a validation rule, but no such " . (count ($missing) > 1 ? 'elements exist' : 'element exists') . '.';
			}
			
			# Ensure that the total field has a third parameter and that all the fields being request supply a 'total' parameter in $this->elements
			if ($validationRule['type'] == 'total') {
				if (!is_numeric ($validationRule['parameter'])) {
					$this->formSetupErrors['validationTotalParameterNonNumeric'] = "The 'maximum' validation rule requires a third, numeric parameter.";
				} else {
					foreach ($validationRule['fields'] as $field) {
						if (isSet ($this->elements[$field]) && !array_key_exists ('total', $this->elements[$field])) {
							$this->formSetupErrors['validationTotalFieldMismatch'] = "Not all the fields selected for the 'maximum' validation rule support totals";
						}
					}
				}
			}
		}
	}
	
	
	# Function to register external problems as registered by the calling application
	function registerProblem ($key, $message)
	{
		# Register the problem
		$this->externalProblems[$key] = $message;
	}
	
	
	# Function to run group validation checks
	#!# Refactor this so that each check is its own function
	function _groupValidationChecks ()
	{
		# Don't do any processing if no rules exist
		if (!$this->validationRules) {return array ();}
		
		# Perform each validation and build an array of problems
		$problems = array ();
		foreach ($this->validationRules as $index => $rule) {
			
			# Get the value of each field, using the presented value unless the widget specifies the value to be used
			$values = array ();
			foreach ($rule['fields'] as $name) {
				$values[$name] = ((isSet ($this->elements[$name]['groupValidation']) && $this->elements[$name]['groupValidation']) ? $this->elements[$name]['data'][$this->elements[$name]['groupValidation']] : $this->elements[$name]['data']['presented']);
			}
			
			# Make an array of non-empty values for use with the 'different' check
			$nonEmptyValues = array ();
			$emptyValues = array ();
			foreach ($values as $name => $value) {
				if (empty ($value)) {
					$emptyValues[$name] = $value;
				} else {
					$nonEmptyValues[$name] = $value;
				}
			}
			
			# For the 'total' check, get the totals from each group
			$total = 0;
			if ($rule['type'] == 'total') {
				foreach ($rule['fields'] as $field) {
					$total += $this->elements[$field]['total'];
				}
			}
			
			# For the 'master' check, we are going to need the name of the master field which will be checked against
			if ($rule['type'] == 'master') {
				foreach ($rule['fields'] as $field) {
					$firstField = $field;
					break;
				}
				$rule['parameter'] = count ($rule['fields']) - 1;
				$rule['fields'] = array ($field);	// Overwrite for the purposes of the error message
			}
			
			# Check the rule
			#!# Ideally refactor to avoid the same list of cases specified as $this->validationTypes
			if (
				   ( ($rule['type'] == 'different') && ($nonEmptyValues) && (count ($nonEmptyValues) != count (array_unique ($nonEmptyValues))) )
				|| ( ($rule['type'] == 'same')      && ((count ($values) > 1) && count (array_unique ($values)) != 1) )
				|| ( ($rule['type'] == 'either')    && (application::allArrayElementsEmpty ($values)) )
				|| ( ($rule['type'] == 'all')       && $nonEmptyValues && $emptyValues )
				|| ( ($rule['type'] == 'total')     && ($total != $rule['parameter']) )
				|| ( ($rule['type'] == 'master')    && $nonEmptyValues && array_key_exists ($firstField, $emptyValues) )
			) {
				$problems['validationFailed' . ucfirst ($rule['type']) . $index] = str_replace (array ('%fields', '%parameter'), array ($this->_fieldListString ($rule['fields']), $rule['parameter']), $this->validationTypes[$rule['type']]);
			}
		}
		
		# Return the problems
		return $problems;
	}
	
	
	# Function to construct a field list string
	function _fieldListString ($fields)
	{
		# Loop through each field name
		foreach ($fields as $name) {
			$names[$name] = ($this->elements[$name]['title'] != '' ? $this->elements[$name]['title'] : ucfirst ($name));
		}
		
		# Construct the list
		$fieldsList = '<strong>' . implode ('</strong> &amp; <strong>', $names) . '</strong>';
		
		# Return the list
		return $fieldsList;
	}
	
	
	/**
	 * Function to output the data
	 * @access private
	 */
	function outputData ($outputType)
	{
		# Assign the presented data according to the output type
		foreach ($this->outputData as $name => $data) {
			$presentedData[$name] = $data[$this->elements[$name]['output'][$outputType]];
		}
		
		# For the processing type, return the results as a raw, uncompiled data array
		if ($outputType == 'processing') {
			return $this->outputDataProcessing ($presentedData);
		}
		
		# Otherwise output the data
		$outputFunction = 'outputData' . ucfirst ($outputType);
		$this->$outputFunction ($presentedData);
	}
	
	
	/**
	 * Set the presentation format for each element
	 * @access private
	 */
	function mergeInPresentationDefaults ()
	{
		# Get the presentation matrix
		$presentationDefaults = $this->presentationDefaults ($returnFullAvailabilityArray = false, $includeDescriptions = false);
		
		# Loop through each element
		foreach ($this->elements as $element => $attributes) {
			
			# Skip if the presentation matrix has no specification for the element (only heading should not do so)
			if (!isSet ($presentationDefaults[$attributes['type']])) {continue;}
			
			# Assign the defaults on a per-element basis
			$defaults = $presentationDefaults[$attributes['type']];
			
			# Slightly hacky special case: for a select type in multiple mode, replace in the defaults the multiple output format instead
			if (($attributes['type'] == 'select') && ($attributes['multiple'])) {
				foreach ($defaults as $outputType => $outputFormat) {
					if (preg_match ("/^([a-z]+) \[when in 'multiple' mode\]$/", $outputType, $matches)) {
						$replacementType = $matches[1];
						$defaults[$replacementType] = $defaults[$outputType];
						unset ($defaults[$outputType]);
					}
				}
			}
			
			# Merge the setup-assigned output formats over the defaults in the presentation matrix
			$this->elements[$element]['output'] = array_merge ($defaults, $attributes['output']);
		}
	}
	
	
	/**
	 * Define presentation output formats
	 * @access private
	 */
	function presentationDefaults ($returnFullAvailabilityArray = false, $includeDescriptions = false)
	{
		# NOTE: Order is: presented -> compiled takes presented if not defined -> rawcomponents takes compiled if not defined
		
		# Define the default presentation output formats
		$presentationDefaults = array (
			
			'checkboxes' => array (
				'_descriptions' => array (
					'rawcomponents'	=> 'An array with every defined element being assigned as itemName => boolean true/false',
					'compiled'		=> 'String of checked items only as selectedItemName1\n,selectedItemName2\n,selectedItemName3',
					'presented'		=> 'As compiled, but in the case of an associative array of values being supplied as selectable items, the visible text version used instead of the actual value',
					'special-setdatatype'		=> 'Chosen items only, listed comma separated with no quote marks',
				),
				'file'				=> array ('rawcomponents', 'compiled', 'presented'),
				'email'				=> array ('compiled', 'rawcomponents', 'presented'),
				'confirmationEmail'	=> array ('presented', 'compiled'),
				'screen'			=> array ('presented', 'compiled'),
				'processing'		=> array ('rawcomponents', 'compiled', 'presented', 'special-setdatatype'),
				'database'			=> array ('compiled'),
			),
			
			'datetime' => array (
				'_descriptions' => array (
					'rawcomponents'	=> "Array containing 'time', 'day', 'month', 'year'",
					'compiled'		=> 'SQL format string of submitted data',
					'presented'		=> 'Submitted data as a human-readable string',
				),
				'file'				=> array ('compiled', 'rawcomponents', 'presented'),
				'email'				=> array ('presented', 'rawcomponents', 'compiled'),
				'confirmationEmail'	=> array ('presented'),
				'screen'			=> array ('presented'),
				'processing'		=> array ('compiled', 'rawcomponents', 'presented'),
				'database'			=> array ('compiled'),
			),
			
			'email' => array (
				'_descriptions' => array (
					'presented'		=> 'Show as unaltered string',
				),
				'file'				=> array ('presented'), #, 'rawcomponents', 'compiled'
				'email'				=> array ('presented'), #, 'rawcomponents', 'compiled'
				'confirmationEmail'	=> array ('presented'), #, 'rawcomponents', 'compiled'
				'screen'			=> array ('presented'), #, 'rawcomponents', 'compiled'
				'processing'		=> array ('presented'), #, 'rawcomponents', 'compiled'
				'database'			=> array ('presented'),
			),
			
			# heading:
			# Never any output for headings
			
			'hidden' => array (
				'_descriptions' => array (
					'rawcomponents'	=> 'The raw array',
					'presented'		=> 'An empty string',
				),
				'file'				=> array ('rawcomponents', 'presented'),
				'email'				=> array ('rawcomponents', 'presented'),
				'confirmationEmail'	=> array ('presented', 'rawcomponents'),
				'screen'			=> array ('presented', 'rawcomponents'),
				'processing'		=> array ('rawcomponents', 'presented'),
				'database'			=> array ('rawcomponents'),
			),
			
			'input' => array (
				'_descriptions' => array (
					'presented'		=> 'Show as unaltered string',
				),
				'file'				=> array ('presented'), #, 'rawcomponents', 'compiled'
				'email'				=> array ('presented'), #, 'rawcomponents', 'compiled'
				'confirmationEmail'	=> array ('presented'), #, 'rawcomponents', 'compiled'
				'screen'			=> array ('presented'), #, 'rawcomponents', 'compiled'
				'processing'		=> array ('presented'), #, 'rawcomponents', 'compiled'
				'database'			=> array ('presented'),
			),
			
			'password' => array (
				'_descriptions' => array (
					'compiled'		=> 'Show as unaltered string',
					'presented'		=> 'Each character of string replaced with an asterisk (*)',
				),
				'file'				=> array ('compiled', 'presented'), #, 'rawcomponents'
				'email'				=> array ('presented', 'compiled'), #, 'rawcomponents'
				'confirmationEmail'	=> array ('presented', 'compiled'), #, 'rawcomponents'	// Compiled allowed even though this means the administrator is allowing them to get their password in plain text via e-mail
				'screen'			=> array ('presented', 'compiled'), #, 'rawcomponents'
				'processing'		=> array ('compiled'), #, 'rawcomponents'
				'database'			=> array ('presented'),
			),
			
			'radiobuttons' => array (
				'_descriptions' => array (
					'rawcomponents'	=> 'An array with every defined element being assigned as itemName => boolean true/false',
					'compiled'		=> 'The (single) chosen item, if any',
					'presented'		=> 'As compiled, but in the case of an associative array of values being supplied as selectable items, the visible text version used instead of the actual value',
				),
				'file'				=> array ('compiled', 'rawcomponents', 'presented'),
				'email'				=> array ('compiled', 'rawcomponents', 'presented'),
				'confirmationEmail'	=> array ('presented', 'compiled'),
				'screen'			=> array ('presented', 'compiled'),
				'processing'		=> array ('compiled', 'rawcomponents', 'presented'),
				'database'			=> array ('compiled'),
			),
			
			'richtext' => array (
				'_descriptions' => array (
					'presented'		=> 'Show as unaltered string, with HTML code visible',
				),
				'file'				=> array ('presented'),
				'email'				=> array ('presented'),
				'confirmationEmail'	=> array ('presented'),
				'screen'			=> array ('presented'),
				'processing'		=> array ('presented'),
				'database'			=> array ('presented'),
			),
			
			'select' => array (
				'_descriptions' => array (
					'rawcomponents'	=> 'An array with every defined element being assigned as itemName => boolean true/false',
					'compiled'		=> 'String of checked items only as selectedItemName1\n,selectedItemName2\n,selectedItemName3',
					'presented'		=> 'As compiled, but in the case of an associative array of values being supplied as selectable items, the visible text version used instead of the actual value',
				),
				'file'				=> array ('compiled', 'rawcomponents', 'presented'),
				"file [when in 'multiple' mode]"		=> array ('rawcomponents', 'compiled', 'presented'),
				'email'				=> array ('compiled', 'presented', 'rawcomponents'),
				'confirmationEmail'	=> array ('presented', 'compiled'),
				'screen'			=> array ('presented', 'compiled'),
				'processing'		=> array ('compiled', 'presented', 'rawcomponents'),
				"processing [when in 'multiple' mode]"	=> array ('rawcomponents', 'compiled', 'presented'),
				'database'			=> array ('compiled'),
			),
			
			'textarea' => array (
				'_descriptions' => array (
					'presented'		=> 'Show as unaltered string',
					'rawcomponents'	=> "
						Depends on 'mode' attribute:
						<ul>
							<li>unspecified/default ('normal'): Unaltered string</li>
							<li>'lines': An array with every line being assigned as linenumber => string</li>
							<li>'coordinates': An array with every line being assigned as linenumber => string</li>
						</ul>
					",
				),
				'file'				=> array ('presented'),
				'email'				=> array ('presented'),
				'confirmationEmail'	=> array ('presented'),
				'screen'			=> array ('presented'),
				'processing'		=> array ('rawcomponents', 'presented'),
				'database'			=> array ('presented'),
			),
			
			'upload' => array (
				'_descriptions' => array (
					'rawcomponents'	=> 'An array with every defined element being assigned as autonumber => filename; this will not show files unzipped but only list the main file with a string description of unzipped files for each main file',
					'compiled'		=> 'An array with every successful element being assigned as filename => attributes; this will include any files automatically unzipped if that was requested',
					'presented'		=> 'Submitted files (and failed uploads) as a human-readable string with the original filenames in brackets',
				),
				'file'				=> array ('rawcomponents', 'presented'),
				'email'				=> array ('presented', 'compiled'),
				'confirmationEmail'	=> array ('presented'),
				'screen'			=> array ('presented'),
				'processing'		=> array ('rawcomponents', 'compiled', 'presented'),
				'database'			=> array ('presented'),
			),
		);
		
		# If the array should return only the defaults rather than full availability, remove the non-defaults
		if (!$returnFullAvailabilityArray) {
			foreach ($presentationDefaults as $type => $attributes) {
				foreach ($attributes as $outputFormat => $availableValues) {
					
					# Don't do anything with descriptions
					if ($outputFormat == '_descriptions') {continue;}
					
					# Overwrite the attributes array with the first item in the array as a non-array value
					$presentationDefaults[$type][$outputFormat] = $availableValues[0];
				}
			}
		}
		
		# If descriptions are not required, remove these from the array
		if (!$includeDescriptions) {
			foreach ($presentationDefaults as $type => $attributes) {
				unset ($presentationDefaults[$type]['_descriptions']);
			}
		}
		
		# Return the defaults matrix
		return $presentationDefaults;
	}
	
	
	/**
	 * Show presentation output formats
	 * @access public
	 */
	function displayPresentationMatrix ()
	{
		# Get the presentation matrix
		$presentationMatrix = $this->presentationDefaults ($returnFullAvailabilityArray = true, $includeDescriptions = true);
		
		# Provide alternative names
		$tableHeadingSubstitutions = array (
			'file' => 'CSV file output',
			'email' => 'E-mail output',
			'confirmationEmail' => 'Confirmation e-mail',
			'screen' => 'Output to screen',
			'processing' => 'Internal processing as an array',
		);
		
		# Build up the HTML, starting with the title and an introduction
		$html  = "\n" . '<h1>Output types and defaults</h1>';
		$html .= "\n\n" . "<p>The following is a list of the supported configurations for how the results of a form are sent as output to the different output formats (CSV file, e-mail, a sender's confirmation e-mail, screen and for further internal processing (i.e. embedded mode) by another program. Items are listed by widget type.</p>";
		$html .= "\n\n" . "<p>Each widget type shows a bullet-pointed list of the shortcut names to be used, (rawcomponents/compiled/presented) and what using each of those will produce in practice.</p>";
		$html .= "\n\n" . "<p>There then follows a chart of the default types for each output format and the other types supported. In most cases, you should find the default gives the best option.</p>";
		$html .= "\n\n" . "<p>(Note: in the few cases where an array-type output is assigned for output to an e-mail or file, the array is converted to a text representation of the array.)</p>";
		
		# Add a jumplist to the widget types
		$html .= "\n" . '<ul>';
		foreach ($presentationMatrix as $type => $attributes) {
			$html .= "\n\t" . "<li><a href=\"#$type\">$type</a></li>";
		}
		$html .= "\n" . '</ul>';
		
		# Add output types each widget type
		foreach ($presentationMatrix as $type => $attributes) {
			$html .= "\n\n" . "<h2 id=\"$type\">" . ucfirst ($type) . '</h2>';
			$html .= "\n" . '<h3>Output types available and defaults</h3>';
			$html .= "\n" . '<ul>';
			foreach ($attributes['_descriptions'] as $descriptor => $description) {
				$html .= "\n\t" . "<li><strong>$descriptor</strong>: " . $description . "</li>";
			}
			$html .= "\n" . '</ul>';
			
			# Start the table of presentation formats, laid out in a table with headings
			$html .= "\n" . '<table class="documentation">';
			$html .= "\n\t" . '<tr>';
			$html .= "\n\t\t" . '<th class="displayformat">Display format</th>';
			$html .= "\n\t\t" . '<th>Default output type</th>';
			$html .= "\n\t\t" . '<th>Others permissible</th>';
			$html .= "\n\t" . '</tr>';
			
			# Each presentation format
			unset ($attributes['_descriptions']);
			foreach ($attributes as $displayFormat => $available) {
				$default = $available[0];
				unset ($available[0]);
				sort ($available);
				$others = implode (', ', $available);
				
				$html .= "\n\t" . '<tr>';
				$html .= "\n\t\t" . "<td class=\"displayformat\"><em>$displayFormat</em></td>";
				$html .= "\n\t\t" . "<td class=\"defaultdisplayformat\"><strong>$default</strong><!-- [" . htmlspecialchars ($presentationMatrix[$type]['_descriptions'][$default]) . ']--></td>';
				$html .= "\n\t\t" . "<td>$others</td>";
				$html .= "\n\t" . '</tr>';
			}
			$html .= "\n" . '</table>';
		}
		
		# Show the result
		$this->html .= $html;
	}
	
	
	/**
	 * Function to return the output data as an array
	 * @access private
	 */
	function outputDataProcessing ($presentedData)
	{
		# Escape the output if necessary
		if ($this->settings['escapeOutput']) {
			
			# Set the default escaping type to '
			if ($this->settings['escapeOutput'] === true) {
				$this->settings['escapeOutput'] = $this->escapeCharacter;
			}
			
			# Loop through the data, whether scalar or one-level array
			$presentedData = $this->escapeOutputIterative ($presentedData, $this->settings['escapeOutput']);
		}
		
		# Return the raw, uncompiled data
		return $presentedData;
	}
	
	
	# Function to perform escaping iteratively
	function escapeOutputIterative ($data, $character)
	{
		# For a scalar, return the escaped value
		if (!is_array ($data)) {
			$data = addslashes ($data);
			#!# Consider adding $data = str_replace ('"', '\\"' . $character, $data); when character is a " - needs further research
			
		} else {
			
			# For an array value, iterate instead
			foreach ($data as $key => $value) {
				$data[$key] = $this->escapeOutputIterative ($value, $character);
			}
		}
		
		# Finally, return the escaped data structure
		return $data;
	}
	
	
	/**
	 * Function to display, in a tabular form, the results to the screen
	 * @access private
	 */
	function outputDataScreen ($presentedData)
	{
		# If nothing has been submitted, return the result directly
		if (application::allArrayElementsEmpty ($presentedData)) {
			return $html = "\n\n" . '<p class="success">No information' . ($this->hiddenElementPresent ? ', other than any hidden data, ' : '') . ' was submitted.</p>';
		}
		
		# Introduce the table
		$html  = "\n\n" . '<p class="success">The information submitted is confirmed as:</p>';
		$html .= "\n" . '<table class="results" summary="Table of results">';
		
		# Assemble the HTML, convert newlines to breaks (without a newline in the HTML), tabs to four spaces, and convert HTML entities
		foreach ($presentedData as $name => $data) {
			
			# Remove empty elements from display
			if (empty ($data) && !$this->configureResultScreenShowUnsubmitted) {continue;}
			
			# If the data is an array, convert the data to a printable representation of the array
			if (is_array ($data)) {$data = application::printArray ($data);}
			
			# Compile the HTML
			$html .= "\n\t<tr>";
			$html .= "\n\t\t" . '<td class="key">' . (isSet ($this->elements[$name]['title']) ? $this->elements[$name]['title'] : $name) . ':</td>';
			$html .= "\n\t\t" . '<td class="value' . (empty ($data) ? ' comment' : '') . '">' . (empty ($data) ? ($this->elements[$name]['type'] == 'hidden' ? '(Hidden data submitted)' : '(No data submitted)') : str_replace (array ("\n", "\t"), array ('<br />', str_repeat ('&nbsp;', 4)), htmlspecialchars ($data))) . '</td>';
			$html .= "\n\t</tr>";
		}
		$html .= "\n" . '</table>';
		
		# Show the constructed HTML
		$this->html .= $html;
	}
	
	
	
	/**
	 * Wrapper function to output the data via e-mail
	 * @access private
	 */
	 function outputDataEmail ($presentedData)
	 {
	 	# Pass through
	 	$this->outputDataEmailTypes ($presentedData, 'email', $this->configureResultEmailShowUnsubmitted);
	 }
	 
	 
	/**
	 * Wrapper function to output the data via e-mail
	 * @access private
	 */
	 function outputDataConfirmationEmail ($presentedData)
	 {
	 	# Pass through
	 	$this->outputDataEmailTypes ($presentedData, 'confirmationEmail', $this->configureResultConfirmationEmailShowUnsubmitted);
	 }
	 
	 
	/**
	 * Function to output the data via e-mail for either e-mail type
	 * @access private
	 */
	function outputDataEmailTypes ($presentedData, $outputType, $showUnsubmitted)
	{
		# If, for the confirmation type, a confirmation address has not been assigned, say so and take no further action
		#!# This should be moved up so that a confirmation e-mail widget is a required field
		if ($outputType == 'confirmationEmail') {
			if (empty ($this->configureResultConfirmationEmailRecipient)) {
				$this->html .= "\n\n" . '<p class="error">A confirmation e-mail could not be sent as no address was given.</p>';
				return false;
			}
		}
		
		# Construct the introductory text, including the IP address for the e-mail type
		$introductoryText = ($outputType == 'confirmationEmail' ? $this->settings['confirmationEmailIntroductoryText'] . ($this->settings['confirmationEmailIntroductoryText'] ? "\n\n\n" : '') : $this->settings['emailIntroductoryText'] . ($this->settings['emailIntroductoryText'] ? "\n\n\n" : '')) . ($outputType == 'email' ? 'Below is a submission from the form' :  'Below is a confirmation of (apparently) your submission from the form') . " at \n" . $_SERVER['_PAGE_URL'] . "\nmade at " . date ('g:ia, jS F Y') . ($this->settings['ip'] ? ', from the IP address ' . $_SERVER['REMOTE_ADDR'] : '') . ($this->settings['browser'] ? (empty ($_SERVER['HTTP_USER_AGENT']) ? '; no browser type information was supplied.' : ', using the browser "' . $_SERVER['HTTP_USER_AGENT']) . '"' : '') . '.';
		
		# Add an abuse notice if required
		if (($outputType == 'confirmationEmail') && ($this->configureResultConfirmationEmailAbuseNotice)) {$introductoryText .= "\n\n(If it was not you who submitted the form, please report it as abuse to " . $this->configureResultConfirmationEmailAdministrator . ' .)';}
		
		# If nothing has been submitted, return the result directly
		if (application::allArrayElementsEmpty ($presentedData)) {
			$resultLines[] = 'No information' . ($this->hiddenElementPresent ? ', other than any hidden data, ' : '') . ' was submitted.';
		} else {
			
			# Assemble a master array of e-mail text, adding the real element name if it's the result rather than confirmation e-mail type. NB: this used to be using str_pad in order to right-align the names, but it doesn't look all that neat in practice: str_pad ($this->elements[$name]['title'], ($this->longestKeyNameLength ($this->outputData) + 1), ' ', STR_PAD_LEFT) . ': ' . $presentedData
			foreach ($presentedData as $name => $data) {
				
				# Remove empty elements from display
				if (empty ($data) && !$showUnsubmitted) {continue;}
				
				# If the data is an array, convert the data to a printable representation of the array
				if (is_array ($presentedData[$name])) {$presentedData[$name] = application::printArray ($presentedData[$name]);}
				
				# Compile the result line
				$resultLines[] = strip_tags ($this->elements[$name]['title']) . (($this->settings['emailShowFieldnames'] && ($outputType == 'email')) ? " [$name]" : '') . ":\n" . $presentedData[$name];
			}
		}
		
		# Select the relevant recipient; for an e-mail type select either the receipient or the relevant field plus suffix
		if ($outputType == 'email') {
			if (application::validEmail ($this->configureResultEmailRecipient)) {
				$recipient = $this->configureResultEmailRecipient;
			} else {
				#!# Makes the assumption of it always being the compiled item. Is this always true? Check also whether it can be guaranteed earlier that only a single item is going to be selected
				$recipient = $this->outputData[$this->configureResultEmailRecipient]['compiled'] . (!empty ($this->configureResultEmailRecipientSuffix) ? $this->configureResultEmailRecipientSuffix : '');
			}
		} else {
			$recipient = $this->configureResultConfirmationEmailRecipient;
		}
		
		# Define the additional headers
		$sender = ($outputType == 'email' ? $this->configureResultEmailAdministrator : $this->configureResultConfirmationEmailAdministrator);
		$additionalHeaders  = 'From: Website feedback <' . $sender . ">\r\n";
		if (($outputType == 'email') && isSet ($this->configureResultEmailCc)) {$additionalHeaders .= 'Cc: ' . implode (', ', $this->configureResultEmailCc) . "\r\n";}
		
		# Add the reply-to if it is set and is not empty and that it has been completed (e.g. in the case of a non-required field)
		if (isSet ($this->configureResultEmailReplyTo)) {
			if ($this->configureResultEmailReplyTo) {
				if (application::validEmail ($this->outputData[$this->configureResultEmailReplyTo]['presented'])) {
					$additionalHeaders .= 'Reply-To: ' . $this->outputData[$this->configureResultEmailReplyTo]['presented'] . "\r\n";
				}
			}
		}
		
		# Define additional mail headers for compatibility
		$additionalHeaders .= $this->fixMailHeaders ($sender);
		
		# Compile the message text
		$message = wordwrap ($introductoryText . "\n\n\n\n" . implode ("\n\n\n", $resultLines));
		
		# Add attachments if required, to the e-mail type only (not confirmation e-mail type), rewriting the message
		if (($outputType == 'email') && $this->attachments) {
			list ($message, $additionalHeaders) = $this->attachmentsMessage ($message, $additionalHeaders, $introductoryText, $resultLines);
		}
		
		# Determine whether to add plain-text headers
		$includeMimeContentTypeHeaders = ($this->attachments ? false : true);
		
		# Send the e-mail
		#!# Add an @ and a message if sending fails (marking whether the info has been logged in other ways)
		$success = application::utf8Mail (
			$recipient,
			$this->configureResultEmailedSubjectTitle[$outputType],
			$message,
			$additionalHeaders,
			NULL,
			$includeMimeContentTypeHeaders
		);
		
		# Delete the attachments that have been mailed, if required
		if (($outputType == 'email') && $this->attachments && $success) {
			foreach ($this->attachments as $index => $attachment) {
				if ($attachment['_attachmentsDeleteIfMailed']) {
					unlink ($this->attachments[$index]['_directory'] . $this->attachments[$index]['name']);
				}
			}
		}
		
		# Confirm sending (or an error) for the confirmation e-mail type
		if ($outputType == 'confirmationEmail') {
			$this->html .= "\n\n" . '<p class="' . ($success ? 'success' : 'error') . '">' . ($success ? 'A confirmation e-mail has been sent' : 'There was a problem sending a confirmation e-mail') . ' to the address you gave (' . $presentedData[$name] = str_replace ('@', '<span>&#64;</span>', htmlspecialchars ($this->configureResultConfirmationEmailRecipient)) . ').</p>';
		}
	}
	
	
	# Function to add attachments; useful articles explaining the background at www.zend.com/zend/spotlight/sendmimeemailpart1.php and www.hollowearth.co.uk/tech/php/email_attachments.php and http://snipplr.com/view/2686/send-multipart-encoded-mail-with-attachments/
	function attachmentsMessage ($message, $additionalHeaders, $introductoryText, $resultLines)
	{
		# Get the maximum total attachment size, per attachment, converting it to bytes, or explicitly false for no limit
		$attachmentsMaxSize = ($this->settings['attachmentsMaxSize'] ? $this->settings['attachmentsMaxSize'] : ini_get ('upload_max_filesize'));
		$attachmentsMaxSize = application::convertSizeToBytes ($attachmentsMaxSize);
		
		# Read the attachments into memory first, or unset the reference to an unreadable attachment, stopping when the attachment size reaches the total limit
		$totalAttachmentsOriginal = count ($this->attachments);
		$attachmentsTotalSize = 0;	// in bytes
		foreach ($this->attachments as $index => $attachment) {
			$attachmentsTotalSizeProposed = $attachmentsTotalSize + $attachment['size'];
			$attachmentSizeAllowable = ($attachmentsTotalSizeProposed <= $attachmentsMaxSize);
			$filename = $attachment['_directory'] . $attachment['name'];
			if ($attachmentSizeAllowable && file_exists ($filename) && is_readable ($filename)) {
				$this->attachments[$index]['_contents'] = chunk_split (base64_encode (file_get_contents ($filename)));
				$attachmentsTotalSize = $attachmentsTotalSizeProposed;
			} else {
				unset ($this->attachments[$index]);
			}
		}
		
		# Attachment counts
		$totalAttachments = count ($this->attachments);
		$totalAttachmentsDifference = ($totalAttachmentsOriginal - $totalAttachments);
		
		# If attachments were successfully read, add them to the e-mail
		if ($this->attachments) {
			
			# Set the end-of-line
			$eol = "\r\n";
			
			# Set the MIME boundary, a unique string
			$mimeBoundary = '<<<--==+X[' . md5( time ()). ']';
			
			# Add MIME headers
			$additionalHeaders .= "MIME-Version: 1.0" . $eol;
			$additionalHeaders .= "Content-Type: multipart/related; boundary=\"{$mimeBoundary}\"" . $eol;
			
			# Push the attachment stuff into the main message area, starting with the MIME introduction
			$message  = $eol;
			$message .= 'This is a multi-part message in MIME format.' . $eol;
			$message .= $eol;
			$message .= '--' . $mimeBoundary . $eol;
			
			# Main message 'attachment'
			$message .= 'Content-type: text/plain; charset="UTF-8"' . $eol;
			$message .= "Content-Transfer-Encoding: 8bit" . $eol;
			$message .= $eol;
			$message .= wordwrap ($introductoryText . "\n\n" . ($totalAttachments == 1 ? 'There is also an attachment.' : "There are also {$totalAttachments} attachments.") . ($totalAttachmentsDifference ? ' ' . ($totalAttachmentsDifference == 1 ? 'One other submitted file was too large to e-mail, so it has' : "{$totalAttachmentsDifference} other submitted files were too large to e-mail, so they have") . " been saved on the webserver. Please contact the webserver's administrator to retrieve " . ($totalAttachmentsDifference == 1 ? 'it' : 'them') . '.' : '') . "\n\n\n\n" . implode ("\n\n\n", $resultLines)) . "{$eol}{$eol}{$eol}" . $eol;
			$message .= '--' . $mimeBoundary;
			
			# Add each attachment, starting with a mini-header for each
			foreach ($this->attachments as $index => $attachment) {
				$message .= $eol;	// End of previous boundary
				$message .= 'Content-Type: ' . ($attachment['type']) . '; name="' . $attachment['name'] . '"' . $eol;
				$message .= "Content-Transfer-Encoding: base64" . $eol;
				$message .= 'Content-Disposition: attachment; filename="' . $attachment['name'] . '"' . $eol;
				$message .= $eol;
				$message .= $attachment['_contents'];
				$message .= $eol;
				$message .= '--' . $mimeBoundary;	// $eol is added in next iteration of loop
			}
			
			# Finish the final boundary
			$message .= '--' . $eol . $eol;
		} else {
			
			# Say that there were no attachments but that the files were saved
			$message  = wordwrap ($introductoryText . "\n\n" . ($totalAttachmentsOriginal == 1 ? 'There is also a submitted file, which was too large to e-mail, so it has' : "There are also {$totalAttachmentsOriginal} submitted files, which were too large to e-mail, so they have") . " been saved on the webserver. Please contact the webserver's administrator to retrieve " . ($totalAttachmentsDifference == 1 ? 'it' : 'them') . '.' . "\n\n\n\n" . implode ("\n\n\n", $resultLines)) . "{$eol}{$eol}{$eol}" . $eol;
		}
		
		# Return the message
		return array ($message, $additionalHeaders);
	}
	
	
	# Function to write additional mailheaders, for when using a mailserver that fails to add key headers
	function fixMailHeaders ($sender)
	{
		# Return an empty string if this functionality is not required
		if (!$this->settings['fixMailHeaders']) {return '';}
		
		# Construct the date, as 'RFC-2822-formatted-date (Timezone)'
		$realDate = date ('r (T)');
		$headers  = "Date: {$realDate}\r\n";
		
		# Construct a message ID, using the application name, defaulting to the current class name
		$applicationName = strtoupper ($this->settings['fixMailHeaders'] === true ? __CLASS__ : $this->settings['fixMailHeaders']);
		$date = date ('YmdHis');
		$randomNumber = mt_rand ();
		if (!isSet ($this->messageIdSequence)) {
			$this->messageIdSequence = 0;
		}
		$this->messageIdSequence++;
		$hostname = $_SERVER['SERVER_NAME'];
		$messageid = "<{$applicationName}.{$date}.{$randomNumber}.{$this->messageIdSequence}@{$hostname}>";
		$headers .= "Message-Id: {$messageid}\r\n";
		
		# Add the return path, being the same as the main sender
		$headers .= "Return-Path: <{$sender}>\r\n";
		
		# Return the headers
		return $headers;
	}
	
	
	/**
	 * Function to write the results to a CSV file
	 * @access private
	 */
	function outputDataFile ($presentedData)
	{
		# Assemble the data into CSV format
		list ($headerLine, $dataLine) = application::arrayToCsv ($presentedData);
		
		# Compile the data, adding in the header if the file doesn't already exist or is empty, and writing a newline after each line
		$data = ((!file_exists ($this->configureResultFileFilename) || filesize ($this->configureResultFileFilename) == 0) ? $headerLine : '') . $dataLine;
		
		# Deal with unicode behaviour
		$unicodeToIso = false;
		$unicodeAddBom = $this->settings['csvBom'];
		
		#!# A check is needed to ensure the file being written to doesn't previously contain headings related to a different configuration
		# Write the data or handle the error
		#!# Replace with file_put_contents when making class PHP5-only
		if (!application::writeDataToFile ($data, $this->configureResultFileFilename, $unicodeToIso, $unicodeAddBom)) {
			$this->html .= "\n\n" . '<p class="error">There was a problem writing the information you submitted to a file. It is likely this problem is temporary - please wait a short while then press the refresh button on your browser.</p>';
		}
	}
	
	
	/**
	 * Function to write the results to a database
	 * @access private
	 */
	#!# Error handling in this function is too basic and needs to be moved higher in the class
	function outputDataDatabase ($presentedData)
	{
		# Connect to the database
		#!# Refactor connectivity as it's now obsolete
		if (! ($this->connection = @mysql_connect ($this->configureResultDatabaseDsn['hostname'], $this->configureResultDatabaseDsn['username'], $this->configureResultDatabaseDsn['password']) && @mysql_select_db ($this->configureResultDatabaseDsn['database']))) {die ('Could not connect: ' . mysql_error());}
#!#		if (!$link = mysql_connect ($this->configureResultDatabaseDsn['hostname'], $this->configureResultDatabaseDsn['username'], $this->configureResultDatabaseDsn['password'])) {die ('Could not connect: ' . mysql_error());}
		mysql_select_db ($this->configureResultDatabaseDsn['database']);
		
		# Design the table schema
		#!# Replace with the output of getDatabaseColumnSpecification()
		$query  = "CREATE TABLE IF NOT EXISTS {$this->configureResultDatabaseTable} (" . "\n";
		$columns[] = '`id` INT AUTO_INCREMENT PRIMARY KEY';
		foreach ($this->elements as $name => $attributes) {
			if (!isSet ($attributes['datatype'])) {continue;}
			$columns[] = "`{$name}` {$attributes['datatype']}" . ($attributes['required'] ? ' NOT NULL' : '') . " COMMENT '{$attributes['title']}'";
		}
		$query .= implode (",\n", $columns);
		$query .= ')';
		
		# Create the table if it doesn't exist
		if (!$result = mysql_query ($query, $link)) {die ('Error creating table: ' . mysql_error ());}
		
		# Compile the result
		$data = array ();
		foreach ($this->elements as $name => $attributes) {
			if (!isSet ($attributes['datatype'])) {continue;}
			$data[$name] = addslashes ((is_array ($this->form[$name]) ? implode ('', $this->form[$name]) : $this->form[$name]));
		}
		#!# Does no data ever arise?
		if ($data) {
			$query  = "INSERT INTO {$this->configureResultDatabaseTable} (" . implode (',', array_keys ($data)) . ") VALUES ('" . implode ("','", array_values ($data)) . "');";
			
			# Add the data
			if (!$result = mysql_query ($query, $link)) {die ('Error inserting data: ' . mysql_error ());}
		}
	}
	
	
	/**
	 * Function to perform the file uploading
	 * @access private
	 */
	function doUploads ()
	{
		# Don't proceed if there are no uploads present
		if (!$this->uploadProperties) {return;}
		
		# Loop through each form element
		foreach ($this->uploadProperties as $name => $arguments) {
			
			# Create arrays of successes and failures
			$successes = array ();
			$failures = array ();
			$actualUploadedFiles = array ();
			
			# Merge the default files list (if there are any such files) into the 'submitted' data, maintaining the indexes but making any new file override the default
			if ($arguments['default']) {
				$this->form[$name] += $arguments['default'];	// += uses right-handed then left-handed - see www.php.net/operators.array , i.e. defaults THEN add on original form[name] (i.e. submitted) value(s)
			}
			
			# Loop through each defined subfield
			for ($subfield = 0; $subfield < $arguments['subfields']; $subfield++) {
				
				# If there is no value for this subfield, skip to the next subfield
				if (!isSet ($this->form[$name][$subfield])) {continue;}
				
				# If the subfield contains merely the default value (i.e. _source = default), then continue
				if (isSet ($this->form[$name][$subfield]['_source']) && ($this->form[$name][$subfield]['_source'] == 'default')) {
					$filename = $this->form[$name][$subfield]['name'];
					$actualUploadedFiles[$arguments['directory'] . $filename] = $this->form[$name][$subfield];
					$successes[$filename]['name'] = $filename;
					$successes[$filename]['namePresented'] = $filename . ' [previously present]';
					continue;
				}
				
				# Get the attributes for this sub-element
				$attributes = $this->form[$name][$subfield];
				
				# Get the file extension preceeded by a dot
				$fileExtension = pathinfo ($attributes['name'], PATHINFO_EXTENSION);
				if (!empty ($fileExtension)) {
					$fileExtension = '.' . $fileExtension;
				}
				
				# Lowercase the extension if necessary
				if ($arguments['lowercaseExtension']) {
					$fileExtension = strtolower ($fileExtension);
				}
				
				# Overwrite the filename if being forced; this always maintains the file extension
				if ($arguments['forcedFileName']) {
					$attributes['name'] = $arguments['forcedFileName'] . $fileExtension;
				}
				
				# Create a shortcut for the filename (just the name, not with the path)
				$filename = $attributes['name'];
				
				# If version control is enabled, do checks to prevent overwriting
				if ($arguments['enableVersionControl']) {
					
					# Check whether a file already exists
					if (file_exists ($existingFile = $arguments['directory'] . $filename)) {
						
						# Check whether the existing file has the same checksum as the file being uploaded
						if (md5_file ($existingFile) != md5_file ($attributes['tmp_name'])) {
							
							# Rename the file by appending the date to it
							$timestamp = date ('Ymd-His');
							$renamed = @rename ($existingFile, $existingFile . ".replaced-{$timestamp}");
							
							# If renaming failed, append an explanation+timestamp to the new file
							if (!$renamed) {
								$filename .= '.forRenamingBecauseCannotMoveOld-' . $timestamp;
							}
						}
					}
				}
				
				# Attempt to upload the file to the (now finalised) destination
				$destination = $arguments['directory'] . $filename;
				if (!move_uploaded_file ($attributes['tmp_name'], $destination)) {
					
					# Create an array of any failed file uploads
					#!# Not sure what happens if this fails, given that the attributes may not exist
					$failures[$filename] = $attributes;
					
				# Continue if the file upload attempt was successful
				} else {
					
					# Fix up the file permission
					umask (0);
					chmod ($destination, 0664);
					
					# Do MIME Type checks (and by now we can be sure that the extension supplied is in the MIME Types list), doing a mime_content_type() check as the value of $elementValue[$subfield]['type'] is not trustworthy and easily fiddled (changing the file extension is enough to fake this)
					if ($arguments['mime']) {
						$extension = pathinfo ($destination, PATHINFO_EXTENSION);	// Best of methods listed at www.cowburn.info/2008/01/13/get-file-extension-comparison/
						$mimeTypeDeclared = $this->mimeTypes[$extension];
						$mimeTypeActual = mime_content_type ($destination);
						if ($mimeTypeDeclared != $mimeTypeActual) {
							$failures[$filename] = $attributes;
							continue;
						}
					}
					
					# Create an array of any successful file uploads. For security reasons, if the filename is modified to prevent accidental overwrites, the original filename is not modified here
					#!# There needs to be a differential between presented and actual data in cases where a different filename is actually written to the disk
					$successes[$filename] = $attributes;
					
					# Unzip the file if required
					#!# Somehow move this higher up so that the same renaming rules apply
					if ($arguments['unzip'] && substr (strtolower ($filename), -4) == '.zip') {
						if ($unzippedFiles = $this->_unzip ($filename, $arguments['directory'], $deleteAfterUnzipping = true)) {
							$listUnzippedFilesMaximum = (is_numeric ($arguments['unzip']) ? $arguments['unzip'] : $this->settings['listUnzippedFilesMaximum']);
							$totalUnzippedFiles = count ($unzippedFiles);
							
							# Add the directory location into each key name
							$actualUploadedFiles = array ();
							$unzippedFilesListPreRenaming = array ();
							foreach ($unzippedFiles as $unzippedFileName => $unzippedFileAttributes) {
								$unzippedFileLocation = $unzippedFileAttributes['_location'];
								unset ($unzippedFileAttributes['_location']);
								$actualUploadedFiles[$unzippedFileLocation] = $unzippedFileAttributes;
								$actualUploadedFiles[$unzippedFileLocation]['_fromZip'] = $filename;
								$unzippedFilesListPreRenaming[] = (isSet ($unzippedFileAttributes['original']) ? $unzippedFileAttributes['original'] : $unzippedFileAttributes['name']);
							}
							
							# Add the (described) zip file to the list of successes
							$successes[$filename]['name'] .= " [automatically unpacked and containing {$totalUnzippedFiles} " . ($totalUnzippedFiles == 1 ? 'file' : 'files') . ($totalUnzippedFiles > $listUnzippedFilesMaximum ? '' : ': ' . implode ('; ', $unzippedFilesListPreRenaming)) . ']';
						}
					} else {
						# Add the directory location into the key name
						$actualUploadedFiles[$arguments['directory'] . $filename] = $attributes;
					}
				}
			}
			
			# Start results
			$data['presented'] = '';
			$data['compiled'] = array ();
			$filenames = array ();
			$presentedFilenames = array ();
			
			# If there were any succesful uploads, assign the compiled output
			if ($successes) {
				
				# Add each of the files to the master array, appending the location for each
				foreach ($actualUploadedFiles as $actualUploadedFileLocation => $attributes) {
					$data['compiled'][$actualUploadedFileLocation] = $attributes;
				}
				
				# Add each of the files to the master array, appending the location for each
				foreach ($successes as $success => $attributes) {
					$filenames[] = $attributes['name'];
					$presentedFilenames[] = (isSet ($attributes['namePresented']) ? $attributes['namePresented'] : $attributes['name']);
				}
				
				# For the compiled version, give the number of files uploaded and their names
				$totalSuccesses = count ($successes);
				$data['presented'] .= $totalSuccesses . ($totalSuccesses > 1 ? ' files' : ' file') . ' (' . implode ('; ', $presentedFilenames) . ') ' . ($totalSuccesses > 1 ? 'were' : 'was') . ' successfully copied over.';
			}
			
			# If there were any failures, list them also
			if ($failures) {
				$totalFailures = count ($failures);
				$data['presented'] .= ($successes ? ' ' : '') . $totalFailures . ($totalFailures > 1 ? ' files' : ' file') . ' (' . implode ('; ', array_keys ($failures)) . ') unfortunately failed to copy over for some unspecified reason.';
			}
			
			# Pad the rawcomponents array out with empty fields upto the number of created subfields; note this HAS to use the original filenames, because an unzipped version could overrun
			$data['rawcomponents'] = array_pad ($filenames, $arguments['subfields'], false);
			
			# Flatten the rawcomponents array if necessary
			if ($this->elements[$name]['flatten'] && ($this->elements[$name]['subfields'] == 1)) {	// subfields check should not be necessary because it should have been switched off already, but this acts as a safety blanket against offsets
				$data['rawcomponents'] = (isSet ($data['rawcomponents'][0]) ? $data['rawcomponents'][0] : false);
			}
			
			# Assign the output data
			$this->elements[$name]['data'] = $data;
		}
	}
	
	
	# Private function to unzip a file on landing
	function _unzip ($file, $directory, $deleteAfterUnzipping = true, $archiveOverwritableFiles = true)
	{
		# Open the zip
		if (!$zip = @zip_open ($directory . $file)) {return false;}
		
		# Loop through each file
		$unzippedFiles = array ();
		while ($zipEntry = zip_read ($zip)) {
			if (!zip_entry_open ($zip, $zipEntry, 'r')) {continue;}
			
			# Read the contents
			$contents = zip_entry_read ($zipEntry, zip_entry_filesize ($zipEntry));
			
			# Determine the zip entry name
			$zipEntryName = zip_entry_name ($zipEntry);
			
			# Ensure the directory exists
			$targetDirectory = dirname ($directory . $zipEntryName) . '/';
			if (!is_dir ($targetDirectory)) {
				umask (0);
				if (!mkdir ($targetDirectory, $this->settings['directoryPermissions'], true)) {
					$deleteAfterUnzipping = false;	// Don't delete the source file if this fails
					continue;
				}
			}
			
			# Skip if the entry itself is a directory (the contained file will have a directory created for it)
			if (substr ($zipEntryName, -1) == '/') {continue;}
			
			# Archive (by appending a timestamp) an existing file if it exists and is different
			$filename = $directory . $zipEntryName;
			$originalIsRenamed = false;
			if ($archiveOverwritableFiles && file_exists ($filename)) {
				if (md5_file ($filename) != md5 ($contents)) {
					$timestamp = date ('Ymd-His');
					# Rename the file, altering the filename reference (using .= )
					$originalIsRenamed = $filename;
					#!# Error here - seems to rename the new rather than the original
					rename ($filename, $filename .= '.replaced-' . $timestamp);
				}
			}
			
			# (Over-)write the new file
			file_put_contents ($filename, $contents);
			
			# Close the zip entry
			zip_entry_close ($zipEntry);
			
			# Assign the files to the master array, emulating a native upload
			$baseFilename = basename ($filename);
			$unzippedFiles[$baseFilename] = array (
				'name' => $baseFilename,
				'type' => (function_exists ('finfo_file') ? finfo_file (finfo_open (FILEINFO_MIME), $filename) : NULL),	// finfo_file is unfortunately a replacement for mime_content_type which is now deprecated
				'tmp_name' => $file,
				'size' => filesize ($filename),
				'_location' => $filename,
			);
			
			# If the original has been renamed, add that
			if ($originalIsRenamed) {
				$unzippedFiles[$baseFilename]['original'] = basename ($originalIsRenamed);
			}
		}
		
		# Close the zip
		zip_close ($zip);
		
		# Delete the submitted file if required
		if ($deleteAfterUnzipping) {
			unlink ($directory . $file);
		}
		
		# Natsort by key
		$unzippedFiles = application::knatsort ($unzippedFiles);
		
		# Sort and return the list of unzipped files
		return $unzippedFiles;
	}
	
	
	# Generic function to generate proxy form widgets from an associated field specification and optional data
	function dataBinding ($suppliedArguments = array ())
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentDefaults = array (
			'database' => NULL,
			'table' => NULL,
			'attributes' => array (),
			'data' => array (),
			'includeOnly' => array (),
			'exclude' => array (),
			'ordering' => array (),
			'enumRadiobuttons' => false,	// Whether to use radiobuttons for ENUM
			'lookupFunction' => false,
			'lookupFunctionParameters' => array (),
			'lookupFunctionAppendTemplate' => false,
			'truncate' => 40,
			'size' => 40,
			'changeCase' => true,	// Convert 'fieldName' field names in camelCase style to 'Standard text'
			'commentsAsDescription' => false,	// Whether to use column comments for the description field rather than for the title field
			'prefix'	=> false,	// What to prefix all field names with (plus _ implied)
			'prefixTitleSuffix'	=> false,	// What to suffix all field titles with
			'intelligence'	=> false,		// Whether to enable intelligent field setup, e.g. password/file*/photograph* become relevant fields and key fields are handled as non-editable
			'floatChopTrailingZeros' => true,	// Whether to replace trailing zeros at the end of a value where there is a decimal point
		);
		
		# Merge the arguments
		$arguments = application::assignArguments ($this->formSetupErrors, $suppliedArguments, $argumentDefaults, 'dataBinding');
		foreach ($arguments as $key => $value) {
			#!# Refactor below to use $arguments array as with other parts of ultimateForm
			$$key = $value;
		}
		
		# Ensure there is a database connection or exit here (errors will already have been thrown)
		if (!$this->databaseConnection) {
			if ($this->databaseConnection === NULL) {	// rather than === NULL, which means no connection requested
				$this->formSetupErrors['dataBindingNoDatabaseConnection'] = 'Data binding has been requested, but no valid database connection has been set up in the main settings.';
			}
			return false;
		}
		
		# Global the dataBinding connection details
		$this->dataBinding = array (
			'database'	=> $database,
			'table'		=> $table,
		);
		
		# Ensure any lookup function has been defined
		if ($lookupFunction && !is_callable ($lookupFunction)) {
			$this->formSetupErrors['dataBindingLookupFunctionInvalid'] = "You specified a lookup function ('<strong>" . (is_array ($lookupFunction) ? implode ('::', $lookupFunction) : $lookupFunction) . "</strong>') for the data binding, but the function does not exist.";
			return false;
		}
		
		# Ensure the user has not set both include and exclude lists
		if ($includeOnly && $exclude) {
			$this->formSetupErrors['dataBindingIncludeExcludeClash'] = 'Values have been set for both includeOnly and exclude when data binding.';
			return false;
		}
		
		# Get the database fields
		if (!$fields = $this->databaseConnection->getFields ($database, $table)) {
			$this->formSetupErrors['dataBindingFieldRetrievalFailed'] = 'The database fields could not be retrieved. Please check that the database library you are using is supported.';
			return false;
		}
		
		# Reorder if required (explicitly, or implicitly via includeOnly)
		if ($includeOnly && !$ordering) {$ordering = $includeOnly;}
		if ($ordering) {
			$ordering = application::ensureArray ($ordering);
			$newFields = array ();
			foreach ($ordering as $field) {
				if (array_key_exists ($field, $fields)) {
					
					# Move fields if set
					$newFields[$field] = $fields[$field];
					unset ($fields[$field]);
				}
			}
			
			# Merge the new fields and the old, with new taking precedence, and remove the old fields
			$fields = array_merge ($newFields, $fields);
			unset ($newFields);
		}
		
		# Loop through the fields in the data, to add widgets
		foreach ($fields as $fieldName => $fieldAttributes) {
			
			# Skip if either: (i) explicitly excluded; (ii) not specifically included or (iii) marked as NULL in the overload array
			if (is_array ($includeOnly) && $includeOnly && !in_array ($fieldName, $includeOnly)) {continue;}
			if (is_array ($exclude) && $exclude && in_array ($fieldName, $exclude)) {continue;}
			if (is_array ($attributes) && (array_key_exists ($fieldName, $attributes))) {
				if ($attributes[$fieldName] === NULL) {continue;}
			}
			
			# Lookup the value if given; NB this can also be supplied in the attribute overloading as defaults
			$value = ((is_array ($data) && (array_key_exists ($fieldName, $data))) ? $data[$fieldName] : $fieldAttributes['Default']);
			
			# Assign the title to be the fieldname by default
			$title = $fieldName;
			
			# Perform a lookup if necessary
			$lookupValues = false;
			$targetDatabase = false;
			$targetTable = false;
			if ($lookupFunction) {
				$parameters = array ($this->databaseConnection, $title, $fieldAttributes['Type']);
				if ($lookupFunctionParameters) {$parameters = array_merge ($parameters, application::ensureArray ($lookupFunctionParameters));}
				$userFunctionResult = call_user_func_array ($lookupFunction, $parameters);
				if (count ($userFunctionResult) != 4) {	// Should be returning an array of four values as per the list() call below
					$this->formSetupErrors['dataBindingLookupFunctionReturnValuesInvalid'] = "You specified a lookup function ('<strong>" . (is_array ($lookupFunction) ? implode ('::', $lookupFunction) : $lookupFunction) . "</strong>') for the data binding, but the function does not return an array of four values as is required.";
					return false;
				}
				list ($title, $lookupValues, $targetDatabase, $targetTable) = $userFunctionResult;
			}
			
			# Convert title from lowerCamelCase to Standard text if necessary
			if ($changeCase) {
				$title = application::changeCase ($title);
			}
			
			# If using table fields comment assign an existent comment as the title, overwriting any amendments to the title already made
			if (!$commentsAsDescription && isSet ($fieldAttributes['Comment']) && $fieldAttributes['Comment']) {
				$title = $fieldAttributes['Comment'];
			}
			
			# Define the standard attributes
			$standardAttributes = array (
				'name' => $fieldName,	// Internal widget name
				'title' => $title,	// Visible name
				'required' => ($fieldAttributes['Null'] != 'YES'),	// Whether a required field
				'default' => $value,
				'datatype' => $fieldAttributes['Type'],
				'description' => ($commentsAsDescription && isSet ($fieldAttributes['Comment']) && $fieldAttributes['Comment'] ? $fieldAttributes['Comment'] : ''),
			);
			
			# If a link template is supplied, place that in, but if it includes a %table/%database template, put it in only if those exist
			if ($lookupFunctionAppendTemplate) {
				$templateHasDatabase = substr_count ($lookupFunctionAppendTemplate, '%database');
				$templateHasTable = substr_count ($lookupFunctionAppendTemplate, '%table');
				if (!$templateHasDatabase && !$templateHasTable) {$useTemplate = true;}	// Use it if no templating requested
				if (($templateHasDatabase && $targetDatabase) || ($templateHasTable && $targetTable)) {$useTemplate = true;}	// Use it if templating is in use and the target database/table is present
				if (($templateHasDatabase && !$targetDatabase) || ($templateHasTable && !$targetTable)) {$useTemplate = false;}	// Ensure both are present if both in use
				if ($useTemplate) {
					#!# Need to deny __refresh as a reserved form name
					$refreshButton = '<input type="submit" value="&#8635;" title="Refresh options" name="__refresh" class="refresh" />';
					$standardAttributes['append'] = str_replace (array ('%database', '%table', '%refresh'), array ($targetDatabase, $targetTable, $refreshButton), $lookupFunctionAppendTemplate);
				}
			}
			
			# Assuming non-forcing of widget type
			$forceType = false;
			
			# Add intelligence rules if required
			if ($intelligence) {
				
				# Fields with 'password' in become password fields, with a proxied confirmation widget
				if (preg_match ('/password/i', $fieldName)) {
					$forceType = 'password';
					$standardAttributes['confirmation'] = true;
					if ($data) {
						$standardAttributes['editable'] = false;
					}
				}
				
				# Richtext fields - text fields with html/richtext in fieldname
				if (preg_match ('/(html|richtext)/i', $fieldName) && (strtolower ($fieldAttributes['Type']) == 'text')) {
					$forceType = 'richtext';
					
					# Use basic toolbar set for fieldnames containing 'basic/mini/simple'
					if (preg_match ('/(basic|mini|simple)/i', $fieldName)) {
						$standardAttributes['editorToolbarSet'] = 'Basic';
					}
				}
				
				# Website fields - for fieldnames containing 'url/website/http'
				if (preg_match ('/(url|website|http)/i', $fieldName)) {
					$forceType = 'input';
					$standardAttributes['regexp'] = '^(http|https)://';
					$standardAttributes['description'] = 'Must begin http://';	// ' or https://' not added to this description just to keep it simple
				}
				
				# Upload fields - fieldname containing photograph/upload or starting/ending with file/document
				if (preg_match ('/(photograph|upload|^file|^document|file$|document$)/i', $fieldName)) {
					$forceType = 'upload';
					$standardAttributes['flatten'] = true;	// Flatten the output so it's a string not an array
					$standardAttributes['subfields'] = 1;	// Specify 1 subfield (which is already the default anyway)
					//$standardAttributes['directory'] = './uploads/';
				}
				
				# Make an auto_increment field not appear
				if ($fieldAttributes['Extra'] == 'auto_increment') {
					if (!$value) {
						continue;	// Skip widget creation (and therefore visibility) if no value
					} else {
						$standardAttributes['editable'] = false;
					}
					/*
					$standardAttributes['discard'] = true;
					
					$standardAttributes['editable'] = false;
					if (!$value) {
						# Show '[Automatically assigned]' as text
						#!# Find a better way to do this in the widget code than this workaround method; perhaps create a 'show' attribute
						$forceType = 'select';
						$standardAttributes['discard'] = true;
						$standardAttributes['values'] = array (1 => '<em class="comment">[Automatically assigned]</em>');	// The value '1' is used to ensure it always validates, whatever the field length or other specification is
						$standardAttributes['forceAssociative'] = true;
						$standardAttributes['default'] = 1;
					}
					*/
				}
				
				# Make a timestamp field not appear
				if ((strtolower ($fieldAttributes['Type']) == 'timestamp') && ($fieldAttributes['Default'] == 'CURRENT_TIMESTAMP')) {
					continue;	// Skip widget creation
				}
			}
			
			# Add per-widget overloading if attributes supplied by the calling application
			if (is_array ($attributes) && (array_key_exists ($fieldName, $attributes))) {
				
				# Convert to hidden type if forced
				if ($attributes[$fieldName] === 'hidden') {
					$fieldAttributes['Type'] = '_hidden';
				} else {
					
					# Amend the type to a specific widget if set
					if (isSet ($attributes[$fieldName]['type'])) {
						if (method_exists ($this, $attributes[$fieldName]['type'])) {
							$forceType = $attributes[$fieldName]['type'];
						}
					}
				}
				
				# Add any headings (which will appear before creating the widget); In the unlikely event that multiple of the same level are needed, '' => "<h2>Foo</h2>\n<h2>Bar</h2>" would have to be used, or the dataBinding will have to split into multiple dataBinding calls
				if (isSet ($attributes[$fieldName]['heading']) && is_array ($attributes[$fieldName]['heading'])) {
					foreach ($attributes[$fieldName]['heading'] as $level => $title) {
						$this->heading ($level, $title);
					}
				}
				
				# Finally, perform the actual overloading the attribute, if the attributes are an array
				if (is_array ($attributes[$fieldName])) {
					$standardAttributes = array_merge ($standardAttributes, $attributes[$fieldName]);
				}
			}
			
			# Prefix the field name if required
			if ($prefix) {	// This will automatically prevent the string '0' anyway
				if ($prefix === '0') {
					$this->formSetupErrors['dataBindingPrefix'] = "A databinding prefix cannot be called '0'";
				}
				if ($prefixTitleSuffix) {
					$standardAttributes['title'] .= $prefixTitleSuffix;	// e.g. a field whose title is "Name" gets a title of "Name (1)" if prefixTitleSuffix = ' (1)'
				}
				$standardAttributes['name'] = $prefix . '_' . $standardAttributes['name'];
				//$standardAttributes['unprefixed'] = $standardAttributes['name'];
				$this->prefixedGroups[$prefix][] = $standardAttributes['name'];
			}
			
			# Deal with looked-up value sets specially, defaulting to select unless the type is forced
			if ($lookupValues && $fieldAttributes['Type'] != '_hidden') {
				$lookupType = 'select';
				if ($forceType && ($forceType == 'checkboxes' || $forceType == 'radiobuttons')) {
					$lookupType = $forceType;
				}
				$this->$lookupType ($standardAttributes + array (
					'forceAssociative' => true,	// Force associative checking of defaults
					#!# What should happen if there's no data generated from a lookup (i.e. empty database table)?
					'values' => $lookupValues,
					'output' => array ('processing' => 'compiled'),
					'truncate' => $truncate,
				));
				continue;	// Don't enter the switch which follows
			}
			
			# Take the type and convert it into a form widget type
			$type = $fieldAttributes['Type'];
			switch (true) {
				
				# Force to a specified type if required
				case ($forceType):
					if (($forceType == 'checkboxes' || $forceType == 'radiobuttons' || $forceType == 'select') && preg_match ('/(enum|set)\(\'(.*)\'\)/i', $type, $matches)) {
						$values = explode ("','", $matches[2]);
						$this->$forceType ($standardAttributes + array (
							'values' => $values,
						));
					} else {
						$this->$forceType ($standardAttributes);
					}
					break;
				
				# Hidden fields - deny editability
				case ($fieldAttributes['Type'] == '_hidden'):
					$this->input ($standardAttributes + array (
						'editable' => false,
						'_visible--DONOTUSETHISFLAGEXTERNALLY' => false,
					));
					break;
				
				# FLOAT (numeric with decimal point) field
				case (preg_match ('/float\(([0-9]+),([0-9]+)\)/i', $type, $matches)):
					if ($floatChopTrailingZeros) {
						if (substr_count ($standardAttributes['default'], '.')) {
							$standardAttributes['default'] = preg_replace ('/0+$/', '', $standardAttributes['default']);
							$standardAttributes['default'] = preg_replace ('/\.$/', '', $standardAttributes['default']);
						}
					}
					$this->input ($standardAttributes + array (
						'maxlength' => ((int) $matches[1] + 2),	// FLOAT(M,D) means "up to M digits in total, of which D digits may be after the decimal point", so maxlength is M + 1 (for the decimal point) + 1 (for a negative sign)
						'regexp' => '^(-?)([0-9]{0,' . ($matches[1] - $matches[2]) . '})((\.)([0-9]{0,' . $matches[2] . '})$|$)',
					));
					break;
				
				# CHAR/VARCHAR (character) field
				case (preg_match ('/(char|varchar)\(([0-9]+)\)/i', $type, $matches)):
					$this->input ($standardAttributes + array (
						'maxlength' => $matches[2],
						# Set the size if a (numeric) value is given and the required size is greater than the size specified
						'size' => ($size && (is_numeric ($size)) && ((int) $matches[2] > $size) ? $size : $matches[2]),
					));
					break;
				
				# INT (numeric) field
				case (preg_match ('/(int|tinyint|smallint|mediumint|bigint)\(([0-9]+)\)/i', $type, $matches)):
					$unsigned = substr_count (strtolower ($type), ' unsigned');
					$this->input ($standardAttributes + array (
						'enforceNumeric' => true,
						'regexp' => ($unsigned ? '^([0-9]*)$' : '^([-0-9]*)$'),
						#!# Make these recognise types without the numeric value after
						'maxlength' => $matches[2],
						'size' => $matches[2] + 1,
					));
					break;
				
				# ENUM (selection) field - explode the matches and insert as values
				case (preg_match ('/enum\(\'(.*)\'\)/i', $type, $matches)):
					$values = explode ("','", $matches[1]);
					foreach ($values as $index => $value) {
						$values[$index] = str_replace ("''", "'", $value);
					}
					$widgetType = ($enumRadiobuttons ? 'radiobuttons' : 'select');
					$this->$widgetType ($standardAttributes + array (
						'values' => $values,
						'output' => array ('processing' => 'compiled'),
					));
					break;
				
				# SET (multiple item) field - explode the matches and insert as values
				case (preg_match ('/set\(\'(.*)\'\)/i', $type, $matches)):
					$values = explode ("','", $matches[1]);
					$setSupportMax = 64;	// MySQL supports max 64 values for SET; #!# This value should be changeable in settings as different database vendor might be in use
					$setSupportSupplied = count ($values);
					if ($setSupportSupplied > $setSupportMax) {
						$this->formSetupErrors['DatabindingSetExcessive'] = "{$setSupportSupplied} values were supplied for the {$fieldName} dataBinding 'SET' field but a maximum of only {$setSupportMax} are supported.";
					} else {
						#!# This one is inconsistent; however, pollenDatabase.php assumes that override values take precedence over $values coming from the eregi match here
						$this->checkboxes (array_merge ($standardAttributes, array (
							'values' => $values,
							'output' => array ('processing' => 'special-setdatatype'),
							'default' => ($value ? (is_array ($value) ? $value : explode (',', $value)) : array ()),	// Value from getData will just be item1,item2,item3
						)));
					}
					break;
				
				# DATE (date) field
				case (preg_match ('/year\(([2|4])\)/i', $type, $matches)):
					$type = 'year';
				case (strtolower ($type) == 'time'):
				case (strtolower ($type) == 'date'):
				case (strtolower ($type) == 'datetime'):
				case (strtolower ($type) == 'timestamp'):
					if (strtolower ($type) == 'timestamp') {
						$type = 'datetime';
						$standardAttributes['default'] = 'timestamp';
						$standardAttributes['editable'] = false;
					}
					$this->datetime ($standardAttributes + array (
						'level' => strtolower ($type),
						#!# Disabled as seemingly incorrect
						/* 'editable' => (strtolower ($type) == 'timestamp'), */
					));
					break;
				
				# BLOB
				case (strtolower ($type) == 'blob'):
				case (strtolower ($type) == 'mediumtext'):
				case (strtolower ($type) == 'text'):
					$this->textarea ($standardAttributes + array (
						// 'cols' => 50,
						// 'rows' => 6,
					));
					break;
				
				#!# Add more here as they are found
				
				# Otherwise throw an error
				default:
					$this->formSetupErrors['dataBindingUnsupportedFieldType'] = "An unknown field type ('{$type}') was found while trying to create a form from the data and fields; as such the form could not be created.";
			}
		}
	}
}



# Subclass to provide a widget
class formWidget
{
	# Class variables
	var $arguments;
	var $settings;
	var $value;
	var $elementProblems = array ();
	var $functionName;
	var $arrayType;
	
	
	# Constructor
	function formWidget (&$form, $suppliedArguments, $argumentDefaults, $functionName, $subargument = NULL, $arrayType = false) {
		
		# Inherit the settings
		$this->settings =& $form->settings;
		
		# Assign the function name
		$this->functionName = $functionName;
		
		# Assign the setup errors array
		$this->formSetupErrors =& $form->formSetupErrors;
		
		# Assign the arguments
		$this->arguments = application::assignArguments ($this->formSetupErrors, $suppliedArguments, $argumentDefaults, $functionName, $subargument);
		
		# Ensure supplied values (values and default are correctly encoded)
		$this->encodeApiSupplied ();
		
		# Register the element name to enable duplicate checking
		$form->registerElementName ($this->arguments['name']);
		
		# Set whether the widget is an array type
		$this->arrayType = $arrayType;
	}
	
	
	# Function to encode supplied value as supplied through the API; does not affect posted data which should not need charset conversion
	function encodeApiSupplied ()
	{
		# Fix values list if there is one
		if (isSet ($this->arguments['values'])) {
			$this->arguments['values'] = application::convertToCharset ($this->arguments['values'], 'UTF-8', $convertKeys = true);
		}
		
		# Fix default value(s)
		if (isSet ($this->arguments['default'])) {
			$this->arguments['default'] = application::convertToCharset ($this->arguments['default'], 'UTF-8', $convertKeys = true);
		}
	}
	
	
	# Function to set the widget's (submitted) value
	function setValue ($value)
	{
		# If an array type, ensure the value is an array, converting where necessary
		if ($this->arrayType) {$value = application::ensureArray ($value);}
		
		# Set the value
		$this->value = $value;
	}
	
	
	# Function to return the arguments
	function getArguments ()
	{
		return $this->arguments;
	}
	
	
	# Function to return the widget's (submitted but processed) value
	function getValue ()
	{
		return $this->value;
	}
	
	
	# Function to determine if a widget is required but empty
	function requiredButEmpty ()
	{
		# Return the value; note that strlen rather than empty() is used because the PHP stupidly allows the string "0" to be empty()
		return (($this->arguments['required']) && (strlen ($this->value) == 0));
	}
	
	
	# Function to return the widget's problems
	function getElementProblems ($problems)
	{
		#!# Temporary: merge in any problems from the object
		if ($problems) {$this->elementProblems += $problems;}
		
		return $this->elementProblems;
	}
	
	
	/**
	 * Function to clean whitespace from a field where requested
	 * @access private
	 */
	function handleWhiteSpace ()
	{
		# Trim white space if required
		if ($this->settings['whiteSpaceTrimSurrounding']) {$this->value = trim ($this->value);}
		
		# Remove white space if that's all there is
		if (($this->settings['whiteSpaceCheatAllowed']) && (trim ($this->value)) == '') {$this->value = '';}
	}
	
	
	# Function to check the minimum length of what is submitted
	function checkMinLength ()
	{
		#!# Move the is_numeric check into the argument cleaning stage
		if (is_numeric ($this->arguments['minlength'])) {
			if (strlen ($this->value) < $this->arguments['minlength']) {
				$this->elementProblems['belowMinimum'] = 'You submitted fewer characters (<strong>' . strlen ($this->value) . '</strong>) than are allowed (<strong>' . $this->arguments['minlength'] . '</strong>).';
			}
		}
	}
	
	
	# Function to check the maximum length of what is submitted
	function checkMaxLength ()
	{
		#!# Move the is_numeric check into the argument cleaning stage
		if (is_numeric ($this->arguments['maxlength'])) {
			if (strlen ($this->value) > $this->arguments['maxlength']) {
				$this->elementProblems['exceedsMaximum'] = 'You submitted more characters (<strong>' . strlen ($this->value) . '</strong>) than are allowed (<strong>' . $this->arguments['maxlength'] . '</strong>).';
			}
		}
	}
	
	
	# Function to prevent multiline submissions in elements (e.g. input) which shouldn't allow line-breaks
	function preventMultilineSubmissions ()
	{
		# Throw an error if an \n or \r line break is found
		if (preg_match ("/([\n\r]+)/", $this->value)) {
			$this->elementProblems['multilineSubmission'] = 'Line breaks are not allowed in field types that do not support these.';
		}
	}
	
	
	/**
	 * Function to clean input from a field to being numeric only
	 * @access private
	 */
	function cleanToNumeric ()
	{
		# End if not required to enforce numeric
		if (!$this->arguments['enforceNumeric']) {return;}
		
		# Don't clean e-mail types
		if ($this->functionName == 'email') {return;}
		
		# Get the data
		#!# Remove these
		$data = $this->value;
		
		#!# Replace with something like this line? :
		#$this->form[$name] = preg_replace ('/[^0-9\. ]/', '', trim ($this->form[$name]));
		
		# Strip replace windows carriage returns with a new line (multiple new lines will be stripped later)
		$data = str_replace ("\r\n", "\n", $data);
		# Turn commas into spaces
		$data = str_replace (',', ' ', $data);
		# Strip non-numeric characters
		$data = preg_replace ("/[^-0-9\.\n\t ]/", '', $data);
		# Replace tabs and duplicated spaces with a single space
		$data = str_replace ("\t", ' ', $data);
		# Replace tabs and duplicated spaces with a single space
		$data = preg_replace ("/[ \t]+/", ' ', $data);
		# Remove space at the start and the end
		$data = trim ($data);
		# Collapse duplicated newlines
		$data = preg_replace ("/[\n]+/", "\n", $data);
		# Remove any space at the start or end of each line
		$data = str_replace ("\n ", "\n", $data);
		$data = str_replace (" \n", "\n", $data);
		
		# Re-assign the data
		#!# Remove these
		$this->value = $data;
	}
	
	
	# Helper function for creating tabindex HTML
	#!# Add tabindex validation, i.e. accept 0-32767, strip leading zeros and confirm is an integer (without decimal places)
	function tabindexHtml ($subwidgetIndex = false)
	{
		# If it's a scalar widget type, return a string
		if (!$subwidgetIndex) {
			return (is_numeric ($this->arguments['tabindex']) ? " tabindex=\"{$this->arguments['tabindex']}\"" : '');
		}
		
		# Add a tabindex value if necessary; a numeric value just adds a tabindex to the first subwidget; an array instead creates a tabindex for any keys which exist in the array
		$tabindexHtml = '';
		if (is_numeric ($this->arguments['tabindex']) && $subwidgetIndex == 0) {
			$tabindexHtml = " tabindex=\"{$this->arguments['tabindex']}\"";
		} else if (is_array ($this->arguments['tabindex']) && array_key_exists ($subwidgetIndex, $this->arguments['tabindex']) && $this->arguments['tabindex'][$subwidgetIndex]) {
			$tabindexHtml = " tabindex=\"{$this->arguments['tabindex'][$subwidgetIndex]}\"";
		}
		
		# Return the value
		return $tabindexHtml;
	}
	
	
	# Perform truncation on the visible part of an array
	function truncate ($values)
	{
		# Loop through and truncating the value's numeric length if necessary
		#!# Needs to take account of multi-dimensional selects
		foreach ($values as $key => $value) {
			#!# Should use a proper &hellip; unicode symbol rather than three dots (...)
			$values[$key] = ($this->arguments['truncate'] && (is_numeric ($this->arguments['truncate'])) ? substr ($value, 0, $this->arguments['truncate']) . ((strlen ($value) > $this->arguments['truncate']) ? ' ...' : '') : $value);
		}
		
		# Return the modified array
		return $values;
	}
	
	
	# Perform regexp checks
	#!# Should there be checking for clashes between disallow and regexp, i.e. so that the widget can never submit?
	#!# Should there be checking of disallow and regexp when editable is false, i.e. so that the widget can never submit?
	function regexpCheck ()
	{
		# End if the form is empty; strlen is used rather than a boolean check, as a submission of the string '0' will otherwise fail this check incorrectly
		if (!strlen ($this->value)) {return false;}
		
		# Regexp checks (for non-e-mail types)
		#!# Allow flexible array ($regexp => $errorMessage) syntax, as with disallow
		if (strlen ($this->arguments['regexp'])) {
			if (!application::pereg ($this->arguments['regexp'], $this->value)) {
				$this->elementProblems['failsRegexp'] = "The submitted information did not match a specific pattern required for this section.";
				return false;
			}
		}
		if (strlen ($this->arguments['regexpi'])) {
			if (!application::peregi ($this->arguments['regexpi'], $this->value)) {
				$this->elementProblems['failsRegexp'] = "The submitted information did not match a specific pattern required for this section.";
				return false;
			}
		}
		
		# 'disallow' regexp checks (for text types)
		if ($this->arguments['disallow'] !== false) {
			
			# If the disallow text is presented as an array, convert the key and value to the disallow patterns and descriptive text; otherwise 
			if (is_array ($this->arguments['disallow'])) {
				foreach ($this->arguments['disallow'] as $disallowRegexp => $disallowErrorMessage) {
					break;
				}
			} else {
				$disallowRegexp = $this->arguments['disallow'];
				$disallowErrorMessage = "The submitted information matched a disallowed pattern for this section.";
			}
			
			# Perform the check
			if (application::pereg ($disallowRegexp, $this->value)) {
				$this->elementProblems['failsDisallow'] = $disallowErrorMessage;
				return false;
			}
		}
		
		# E-mail check (for e-mail type)
		if ($this->functionName == 'email') {
			
			# Do splitting if required, by comma/semi-colon/space with any spaces surrounding
			$addresses = array ($this->value);	// By default, make it a list of one
			if ($this->arguments['several']) {
				$addresses = application::emailListStringToArray ($this->value);
			}
			
			# Loop through each address (which may be just one)
			$invalidAddresses = array ();
			foreach ($addresses as $address) {
				if (!application::validEmail ($address)) {
					$invalidAddresses[] = $address;
				}
			}
			
			# Report if invalid
			if ($invalidAddresses) {
				$this->elementProblems['invalidEmail'] = (count ($addresses) == 1 ? 'The e-mail address' : (count ($invalidAddresses) == 1 ? 'An e-mail address' : 'Some e-mail addresses')) . ' (' . htmlspecialchars (implode (', ', $invalidAddresses)) . ') you gave ' . (count ($invalidAddresses) == 1 ? 'appears' : 'appear') . ' to be invalid.';
				return false;
			}
		}
		
		# Otherwise signal OK
		return true;
	}
	
	
	# Function to check for spam submissions
	function antispamCheck ()
	{
		# Antispam checks
		if ($this->arguments['antispam']) {
			if (preg_match ($this->settings['antispamRegexp'], $this->value)) {
				$this->elementProblems['failsAntispam'] = "The submitted information matched disallowed text for this section.";
			}
		}
	}
	
	
	# Function to check for uniqueness
	function uniquenessCheck ($caseSensitiveComparison = false, $trim = true)
	{
		# End if no current values supplied
		if (!$this->arguments['current']) {return NULL;}
		
		# End if array is multi-dimensional
		if (application::isMultidimensionalArray ($this->arguments['current'])) {
			$this->formSetupErrors['currentIsMultidimensional'] = "The list of current values pre-supplied for the '{$this->arguments['name']}' field cannot be multidimensional.";
			return false;
		}
		
		# Ensure the current values are an array
		$this->arguments['current'] = application::ensureArray ($this->arguments['current']);
		
		# Trim values
		if ($trim) {
			$this->arguments['current'] = application::arrayTrim ($this->arguments['current']);
		}
		
		# Find clashes
		if ($caseSensitiveComparison) {
			$clash = in_array ($this->value, $this->arguments['current']);
		} else {
			$clash = application::iin_array ($this->value, $this->arguments['current']);
		}
		
		# Throw user error if any clashes
		if ($clash) {
			$this->elementProblems['valueMatchesCurrent'] = 'This value already exists - please enter another.';
		}
	}
}


#!# Make the file specification of the form more user-friendly (e.g. specify / or ./ options)
#!# Do a single error check that the number of posted elements matches the number defined; this is useful for checking that e.g. hidden fields are being posted
#!# Add form setup checking validate input types like cols= is numeric, etc.
#!# Add a warnings flag in the style of the errors flagging to warn of changes which have been made silently
#!# Need to add configurable option (enabled by default) to add headings to new CSV when created
#!# Ideally add a catch to prevent the same text appearing twice in the errors box (e.g. two widgets with "details" as the descriptive text)
#!# Enable maximums to other fields
#!# Complete the restriction notices
#!# Add a CSS class to each type of widget so that more detailed styling can be applied
#!# Enable locales, e.g. ordering month-date-year for US users
#!# Consider language localisation (put error messages into a global array, or use gettext)
#!# Add in <span>&#64;</span> for on-screen e-mail types
#!# Apache setup needs to be carefully tested, in conjunction with php.net/ini-set and php.net/configuration.changes
#!# Add links to the id="$name" form elements in cases of USER errors (not for the templating mode though)
#!# Need to prevent the form code itself being overwritable by uploads or CSV writing, by doing a check on the filenames
#!# Add <label> and (where appropriate) <fieldset> support throughout - see also http://www.aplus.co.yu/css/styling-form-fields/ ; http://www.bobbyvandersluis.com/articles/formlayout.php ; http://www.simplebits.com/notebook/2003/09/16/simplequiz_part_vi_formatting.html ; http://www.htmldog.com/guides/htmladvanced/forms/ ; checkbox & radiobutton have some infrastructure written (but commented out) already
#!# Full support for all attributes listed at http://www.w3schools.com/tags/tag_input.asp
#!# Number validation: validate numbers with strval() and intval() or floatval() - www.onlamp.com/pub/a/php/2004/08/26/PHPformhandling.html
#!# Move to in_array with strict third parameter (see fix put in for 1.9.9 for radiobuttons)
# Remove display_errors checking misfeature or consider renaming as disableDisplayErrorsCheck
# Enable specification of a validation function (i.e. callback for checking a value against a database)
# Element setup errors should result in not bothering to create the widget; this avoids more offset checking like that at the end of the radiobuttons type in non-editable mode
# Multi-select combo box like at http://cross-browser.com/x/examples/xselect.php
# Consider highlighting in red areas caught by >validation
# Optgroup setting to allow multiple appearances of the same item
#!# Deal with encoding problems - see http://skew.org/xml/misc/xml_vs_http/#troubleshooting
#!# $resultLines[] should have the [techName] optional
# Antispam Captcha option
# Support for select::regexp needed - for cases where a particular option needs to become disabled when submitting a dataBinded form
# Consider issue of null bytes in ereg - http://uk.php.net/manual/en/ref.regex.php#74258 - probably migrate to preg_ anyway, as PHP6 deprecates ereg
# Consider grouping/fieldset and design issues at http://www.sitepoint.com/print/fancy-form-design-css/
# Deal with widget name conversion of dot to underscore: http://uk2.php.net/manual/en/language.types.array.php#52124
# Check more thoroughly against XSS at http://ha.ckers.org/xss.html
# Add slashes and manual \' replacement need to be re-considered

# Version 2 feature proposals
#!# Self-creating form mode
#!# Full object orientation - change the form into a package of objects
#!#		Change each input type to an object, with a series of possible checks that can be implemented - class within a class?
#!# 	Change the output methods to objects
#!# Allow multiple carry-throughs, perhaps using formCarried[$formNumber][...]: Add carry-through as an additional array section; then translate the additional array as a this-> input to hidden fields.
#!# Enable javascript as an option
		# On-submit disable switch bouncing
		# Assign only the final submit button as the one accepting a 'return' when using multiple submits (refresh)
#!# 	Use ideas in http://www.sitepoint.com/article/1273/3 for having js-validation with an icon
		# http://www.tetlaw.id.au/view/javascript/really-easy-field-validation   may be a useful library
#!# 	Style like in http://www.sitepoint.com/examples/simpletricks/form-demo.html [linked from http://www.sitepoint.com/article/1273/3]
#!# Add AJAX validation flag See: http://particletree.com/features/degradable-ajax-form-validation/ (but modified version needed because this doesn't use Unobtrusive DHTML - see also http://particletree.com/features/a-guide-to-unobtrusive-javascript-validation/ )


?>