<?php

/**
 * A class for the easy creation of webforms.
 * 
 * ## NOTE: the API (i.e. syntax for creating forms) is subject to change at present!! ##
 * 
 * SUPPORTS:
 * - Form stickyness
 * - Field types: input, password, textarea, select, checkboxes (a minimum number can be specified), radio buttons
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
 * 
 * REQUIREMENTS:
 * - PHP4.1 or above
 * - Runs in register_globals OFF mode for security
 * - Requires libraries application.php and pureContent.php
 * 
 * APACHE ENVIRONMENT SETUP
 * 
 * The following are required for the script to work correctly; if not, an error will be shown
 * If attempting to set in .htaccess, remove admin_ from the directives
 * 
 * <code>
 * php_admin_flag register_globals 0
 * php_admin_flag display_errors 0
 * php_admin_flag magic_quotes_gpc 0
 * php_admin_value error_reporting 2047
 * 
 * # If using file uploads also include the following and set a suitable amount in MB; upload_max_filesize must not be more than post_max_size
 * php_admin_flag file_uploads 1
 * php_admin_value upload_max_filesize 10M // Only way of setting the maximum size
 * php_admin_value post_max_size 10M
 * </code>
 * 
 * @package ultimateForm
 * @license	http://opensource.org/licenses/gpl-license.php GNU Public License
 * @author	{@link http://www.geog.cam.ac.uk/contacts/webmaster.html Martin Lucas-Smith}, University of Cambridge 2003-4
 * @copyright Copyright © 2003-5, Martin Lucas-Smith, University of Cambridge
 * @version 0.99b1
 */
class form
{
	## Prepare variables ##
	
	# Principal arrays
	var $elements;					// Master array of form element setup
	var $form;						// Master array of posted form data
	var $outputData;				// Master array of arranged data for output
	var $outputMethods = array ();	// Master array of output methods
	
	# Main variables
	var $formName;								// The name of the form
	var $location;								// The location where the form is submitted to
	var $duplicatedElementNames = array ();		// The array to hold any duplicated form field names
	var $formSetupErrors = array ();			// Array of form setup errors, to which any problems can be added
	var $elementProblems = array ();			// Array of submitted element problems
	
	# State control
	var $formPosted;							// Flag for whether the form has been posted
	var $formDisplayed = false;					// Flag for whether the form has been displayed
	var $formSetupOk = false;					// Flag for whether the form has been set up OK
	var $headingTextCounter = 0;				// Counter to enable uniquely-named fields for non-form elements (i.e. headings) #!# Get rid of this somehow
	var $uploadElementPresent = false;			// Flag for whether the form includes one or more upload elements
	var $hiddenElementPresent = false;			// Flag for whether the form includes one or more hidden elements
	
	# Output configuration
	var $configureResultEmailRecipient;							// The recipient of an e-mail
	var $configureResultEmailRecipientSuffix;					// The suffix used when a select field is used as the e-mail receipient but the selectable items are only the prefix to the address
	var $configureResultEmailAdministrator;						// The from field of an e-mail
	var $configureResultFileFilename;							// The file name where results are written
	var $configureResultConfirmationEmailRecipient = '';		// The recipient of any confirmation e-mail
	var $configureResultConfirmationEmailAbuseNotice = true;	// Whether to include an abuse report notice in any confirmation e-mail sent
	var $configureResultEmailedSubjectTitle = array ();			// An array to hold the e-mail subject title for either e-mail result type
	
	# Constants
	var $timestamp;
	
	## Load initial state and assign settings ##
	
	/**
	 * Constructor
	 * @param array $arguments Settings
	 */
	function form ($arguments = array ())
	{
		# Load the application support library which itself requires the pureContent framework file, pureContent.php; this will clean up $_SERVER
		require_once ('application.php');
		
		# Load the date processing library
		require_once ('datetime.php');
		
		# Assign constants
		$this->timestamp = date ('Y-m-d H:m:s');
		
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentsSpecification = array (
			'formName'						=> 'form',									# Name of the form
			'showPresentationMatrix'		=> false,									# Whether to show the presentation defaults
			'displayTitles'					=> true,									# Whether to show user-supplied titles for each widget
			'displayDescriptions'			=> true,									# Whether to show user-supplied descriptions for each widget
			'displayRestrictions'			=> true,									# Whether to show/hide restriction guidelines
			'display'						=> 'tables',								# Whether to display the form using 'tables', 'css' (CSS layout) or 'paragraphs'
			'debug'							=> false,									# Whether to switch on debugging
			'developmentEnvironment'		=> false,									# Whether to run in development mode
			'displayColons'					=> true,									# Whether to show colons after the initial description
			'whiteSpaceTrimSurrounding'		=> true,									# Whether to trim surrounding white space in any forms which are submitted
			'whiteSpaceCheatAllowed'		=> false,									# Whether to allow people to cheat submitting whitespace only in required fields
			'formCompleteText'				=> 'Many thanks for your input.',			# The form completion text
			'showFormCompleteText'			=> true,									# Whether to show the form complete text when completed
			'submitButtonAtEnd'				=> true,									# Whether the submit button appears at the end or the start of the form
			'submitButtonText'				=> 'Submit!',								# The form submit button text
			'submitButtonAccesskey'			=> 's',										# The form submit button accesskey
			'resetButtonText'				=> 'Clear changes',							# The form reset button
			'resetButtonAccesskey'			=> 'r',										# The form reset button accesskey
			'warningMessage'				=> 'The highlighted items have not been completed successfully.',	# The form incompletion message
			'resetButtonVisible'			=> false,									# Whether the reset button is visible
			'requiredFieldIndicatorDisplay'	=> true,									# Whether the required field indicator is to be displayed
			'submitTo'						=> $_SERVER['REQUEST_URI'],					# The form processing location if being overriden
			'autoCenturyConversionEnabled'	=> true,									# Whether years entered as two digits should automatically be converted to four
			'autoCenturyConversionLastYear'	=> 69,										# The last two figures of the last year where '20' is automatically prepended
			'nullText'						=> 'Please select value',					# The 'null' text for e.g. selection boxes
			'opening'						=> false,									# Optional starting datetime as an SQL string
			'closing'						=> false,									# Optional closing datetime as an SQL string
			'validUsers'					=> false,									# Optional valid user(s) - if this is set, a user will be required. To set, specify string/array of valid user(s), or '*' to require any user
			'user'							=> false,									# Explicitly-supplied username (if none specified, will check for REMOTE_USER being set
			'userKey'						=> false,									# Whether to log the username, as the key
			'loggedUserUnique'				=> false,									# Run in user-uniqueness mode, making the key of any CSV the username and checking for resubmissions
			'timestamping'					=> false,									# Add a timestamp to any CSV entry
		);
		
		# Import supplied arguments assign defaults; NB: array_merge is NOT used, as this could create unintended globals
		foreach ($argumentsSpecification as $argument => $defaultValue) {
			$this->{$argument} = (isSet ($arguments[$argument]) ? $arguments[$argument] : $defaultValue);
		}
		
		# Ensure the userlist is an array, whether empty or otherwise
		$this->validUsers = application::ensureArray ($this->validUsers);
		
		# If no user is supplied, attempt to obtain the REMOTE_USER (if one exists) as the default
		if (!$this->user) {$this->user = (isSet ($_SERVER['REMOTE_USER']) ? $_SERVER['REMOTE_USER'] : false);}
		
		# Assign the opening and closing time
		$this->opening = ($this->opening ? strtotime ($this->opening . ' GMT') : NULL);
		$this->closing = ($this->closing ? strtotime ($this->closing . ' GMT') : NULL);
		
		# Assign whether the form has been posted or not
		$this->formPosted = (isSet ($_POST[$this->formName]));
		
		# Add in the hidden security fields if required, having verified username existence if relevant; these need to go at the start so that any username is set as the key
		$this->addHiddenSecurityFields ();
		
		# Import the posted data if the form is posted; this has to be done initially otherwise the input widgets won't have anything to reference
		if ($this->formPosted) {$this->form = $_POST[$this->formName];}
		
		# If there are files posted, merge the multi-part posting of files to the main form array, effectively emulating the structure of a standard form input type
		if (!empty ($_FILES[$this->formName])) {$this->mergeFilesIntoPost ();}
	}
	
	
	## Supported form widget types ##
	
	
	/**
	 * Create a standard input widget
	 * @param array $arguments Supplied arguments - see template
	 */
	function input ($arguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentsSpecification = array (
			'input' => array (
				'elementName'			=> NULL,	# Name of the element
				'title'					=> '',		# Introductory text
				'elementDescription'	=> '',		# Description text
				'outputFormat'			=> array (),# Presentation format (CURRENTLY UNDOCUMENTED)
				'required'				=> false,	# Whether required or not
				'enforceNumeric'		=> false,	# Whether to enforce numeric input or not (optional; defaults to false)
				'size'					=> 30,		# Visible size (optional; defaults to 30)
				'maxlength'				=> '',		# Maximum length (optional; defaults to no limit)
				'initialValue'			=> '',		# Default value (optional)
				'regexp'				=> '',		# Regular expression against which the submission must validate (optional)
		));
		
		# Ensure that arguments with a non-null default value are set (throwing an error if not), or assign the default value if none is specified
		foreach ($argumentsSpecification as $fieldType => $availableArguments) {
			foreach ($availableArguments as $argument => $defaultValue) {
				if (is_null ($defaultValue)) {
					if (!isSet ($arguments[$argument])) {
						$this->formSetupErrors['absent' . ucfirst ($fieldType) . ucfirst ($argument)] = "No '$argument' has been set for a specified $fieldType field.";
						${$argument} = $fieldType;
					} else {
						${$argument} = $arguments[$argument];
					}
				} else {
					${$argument} = (isSet ($arguments[$argument]) ? $arguments[$argument] : $defaultValue);
				}
			}
		}
		
		# Check whether the element name already been used accidentally (i.e. two fields with the same name) and if so add it to the array of duplicated element names
		if (isSet ($this->elements[$elementName])) {$this->duplicatedElementNames[] = $elementName;}
		
		# Make sure the element is not empty
		if (!isSet ($this->form[$elementName])) {$this->form[$elementName] = '';}
		
		# Handle whitespace issues
		$this->form[$elementName] = $this->handleWhiteSpace ($this->form[$elementName]);
		
		# Run maxlength checking server-side
		if (is_numeric ($maxlength)) {
			if (strlen ($this->form[$elementName]) > $maxlength) {
				$elementProblems['exceedsMaximum'] = 'You submitted more characters (<strong>' . strlen ($this->form[$elementName]) . '</strong>) than are allowed (<strong>' . $maxlength . '</strong>).';
			}
		}
		
		# Clean up the submitted data if required to enforce numeric
		if ($enforceNumeric) {$this->form[$elementName] = $this->cleanToNumeric ($this->form[$elementName]);}
		
		# If the form is not empty, apply a regular expression check if required
		if ($this->form[$elementName] != '') {
			if ($regexp != '') {
				if (!ereg ($regexp, $this->form[$elementName])) {
					$elementProblems['failsRegexp'] = 'The submitted information did not match a specific pattern required for this section.';
				}
			}
		}
		
		# Check whether the field satisfies any requirement for a field to be required
		$requiredButEmpty = (($required) && ($this->form[$elementName] == ''));
		
		# Assign the initial value if the form is not posted (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid)
		if (!$this->formPosted) {$this->form[$elementName] = $initialValue;}
		
		# Describe restrictions on the widget
		if ($enforceNumeric) {$restriction = 'Must be numeric';}
		
		# Define the widget's core HTML
		$widgetHtml = '<input name="' . $this->formName . "[$elementName]\" type=\"text\" size=\"$size\"" . ($maxlength != '' ? " maxlength=\"$maxlength\"" : '') . " value=\"" . htmlentities ($this->form[$elementName]) . '" />';
		
		# Add the widget to the master array for eventual processing
		$this->elements[$elementName] = array (
			'type' => 'input',
			'html' => $widgetHtml,
			'title' => $title,
			'description' => $elementDescription,
			'restriction' => (isSet ($restriction) ? $restriction : false),
			'problems' => (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $required,
			'requiredButEmpty' => $requiredButEmpty,
			'suitableAsEmailTarget' => false,
			'outputFormat' => $outputFormat,
		);
	}
	
	
	/**
	 * Create a password widget (same as an input widget but using the HTML 'password' type)
	 * @param array $arguments Supplied arguments - see template
	 */
	function password ($arguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentsSpecification = array (
			'password' => array (
				'elementName'			=> NULL,	# Name of the element
				'title'					=> '',		# Introductory text
				'elementDescription'	=> '',		# Description text
				'outputFormat'			=> array (),# Presentation format (CURRENTLY UNDOCUMENTED)
				'required'				=> false,	# Whether required or not
				'enforceNumeric'		=> false,	# Whether to enforce numeric input or not (optional; defaults to false)
				'size'					=> 30,		# Visible size (optional; defaults to 30)
				'maxlength'				=> '',		# Maximum length (optional; defaults to no limit)
				'initialValue'			=> '',		# Default value (optional)
				'regexp'				=> '',		# Regular expression against which the submission must validate (optional)
		));
		
		# Ensure that arguments with a non-null default value are set (throwing an error if not), or assign the default value if none is specified
		foreach ($argumentsSpecification as $fieldType => $availableArguments) {
			foreach ($availableArguments as $argument => $defaultValue) {
				if (is_null ($defaultValue)) {
					if (!isSet ($arguments[$argument])) {
						$this->formSetupErrors['absent' . ucfirst ($fieldType) . ucfirst ($argument)] = "No '$argument' has been set for a specified $fieldType field.";
						${$argument} = $fieldType;
					} else {
						${$argument} = $arguments[$argument];
					}
				} else {
					${$argument} = (isSet ($arguments[$argument]) ? $arguments[$argument] : $defaultValue);
				}
			}
		}
		
		# Check whether the element name already been used accidentally (i.e. two fields with the same name) and if so add it to the array of duplicated element names
		if (isSet ($this->elements[$elementName])) {$this->duplicatedElementNames[] = $elementName;}
		
		# Make sure the element is not empty
		if (!isSet ($this->form[$elementName])) {$this->form[$elementName] = '';}
		
		# Handle whitespace issues
		$this->form[$elementName] = $this->handleWhiteSpace ($this->form[$elementName]);
		
		# Clean up the submitted data if required to enforce numeric
		if ($enforceNumeric) {$this->form[$elementName] = $this->cleanToNumeric ($this->form[$elementName]);}
		
		# Run maxlength checking server-side
		if (is_numeric ($maxlength)) {
			if (strlen ($this->form[$elementName]) > $maxlength) {
				$elementProblems['exceedsMaximum'] = 'You submitted more characters (<strong>' . strlen ($this->form[$elementName]) . '</strong>) than are allowed (<strong>' . $maxlength . '</strong>).';
			}
		}
		
		# Apply a regular expression check if required
		if ($regexp != '') {
			if (!ereg ($regexp, $this->form[$elementName])) {
				$elementProblems['failsRegexp'] = 'The submitted information did not match a specified pattern required for this section.';
			}
		}
		
		# Check whether the field satisfies any requirement for a field to be required
		$requiredButEmpty = (($required) && ($this->form[$elementName] == ''));
		
		# Assign the initial value if the form is not posted (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid)
		if (!$this->formPosted) {$this->form[$elementName] = $initialValue;}
		
		# Describe restrictions on the widget
		if ($enforceNumeric) {
			$restriction = 'Must be numeric';
		}
		
		# Define the widget's core HTML
		$widgetHtml = '<input name="' . $this->formName . "[$elementName]\" type=\"password\" size=\"$size\"" . ($maxlength != '' ? " maxlength=\"$maxlength\"" : '') . " value=\"" . htmlentities ($this->form[$elementName]) . '" />';
		
		# Add the widget to the master array for eventual processing
		$this->elements[$elementName] = array (
			'type' => 'password',
			'html' => $widgetHtml,
			'title' => $title,
			'description' => $elementDescription,
			'restriction' => (isSet ($restriction) ? $restriction : false),
			'problems' => (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $required,
			'requiredButEmpty' => $requiredButEmpty,
			'suitableAsEmailTarget' => false,
			'outputFormat' => $outputFormat,
		);
	}
	
	
	/**
	 * Create an input field requiring a syntactically valid e-mail address; if a more specific e-mail validation is required, use $form->input and supply an e-mail validation regexp
	 * @param array $arguments Supplied arguments - see template
	 */
	function email ($arguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentsSpecification = array (
			'email' => array (
				'elementName'			=> NULL,	# Name of the element
				'title'					=> '',		# Introductory text
				'elementDescription'	=> '',		# Description text
				'outputFormat'			=> array (),# Presentation format (CURRENTLY UNDOCUMENTED)
				'required'				=> false,	# Whether required or not
				'size'					=> 30,		# Visible size (optional; defaults to 30)
				'maxlength'				=> '',		# Maximum length (optional; defaults to no limit)
				'initialValue'			=> '',		# Default value (optional)
		));
		
		# Ensure that arguments with a non-null default value are set (throwing an error if not), or assign the default value if none is specified
		foreach ($argumentsSpecification as $fieldType => $availableArguments) {
			foreach ($availableArguments as $argument => $defaultValue) {
				if (is_null ($defaultValue)) {
					if (!isSet ($arguments[$argument])) {
						$this->formSetupErrors['absent' . ucfirst ($fieldType) . ucfirst ($argument)] = "No '$argument' has been set for a specified $fieldType field.";
						${$argument} = $fieldType;
					} else {
						${$argument} = $arguments[$argument];
					}
				} else {
					${$argument} = (isSet ($arguments[$argument]) ? $arguments[$argument] : $defaultValue);
				}
			}
		}
		
		# Check whether the element name already been used accidentally (i.e. two fields with the same name) and if so add it to the array of duplicated element names
		if (isSet ($this->elements[$elementName])) {$this->duplicatedElementNames[] = $elementName;}
		
		# Make sure the element is not empty
		if (!isSet ($this->form[$elementName])) {$this->form[$elementName] = '';}
		
		# Handle whitespace issues
		$this->form[$elementName] = $this->handleWhiteSpace ($this->form[$elementName]);
		
		# Run maxlength checking server-side
		if (is_numeric ($maxlength)) {
			if (strlen ($this->form[$elementName]) > $maxlength) {
				$elementProblems['exceedsMaximum'] = "You submitted more characters (<strong>" . strlen ($this->form[$elementName]) . "</strong>) than are allowed (<strong>" . $maxlength . '</strong>).';
			}
		}
		
		# If this field has been entered and it's not a valid e-mail address, add this as a problem [regexp from www.zend.com/zend/spotlight/ev12apr.php ]
		if (($this->form[$elementName] != '') && (!application::validEmail ($this->form[$elementName]))) {
			$elementProblems['invalidEmail'] = 'The e-mail address you gave appears to be invalid.';
		}
		
		# Check whether the field satisfies any requirement for a field to be required
		$requiredButEmpty = (($required) && ($this->form[$elementName] == ''));
		
		# Assign the initial value if the form is not posted (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid)
		if (!$this->formPosted) {$this->form[$elementName] = $initialValue;}
		
		# Describe restrictions on the widget
		$restriction = 'Must be valid';
		
		# Define the widget's core HTML
		$widgetHtml = '<input name="' . $this->formName . "[$elementName]\" type=\"text\" size=\"$size\"" . ($maxlength != '' ? " maxlength=\"$maxlength\"" : '') . " value=\"" . htmlentities ($this->form[$elementName]) . '" />';
		
		# Add the widget to the master array for eventual processing
		$this->elements[$elementName] = array (
			'type' => 'email',
			'html' => $widgetHtml,
			'title' => $title,
			'description' => $elementDescription,
			'restriction' => (isSet ($restriction) ? $restriction : false),
			'problems' => (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $required,
			'requiredButEmpty' => $requiredButEmpty,
			'suitableAsEmailTarget' => true,
			'outputFormat' => $outputFormat,
		);
	}
	
	
	/**
	 * Create a textarea box
	 * @param array $arguments Supplied arguments - see template
	 */
	function textarea ($arguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentsSpecification = array (
			'textarea' => array (
				'elementName'			=> NULL,	# Name of the element
				'title'					=> '',		# Introductory text
				'elementDescription'	=> '',		# Description text
				'outputFormat'			=> array (),# Presentation format (CURRENTLY UNDOCUMENTED)
				'required'				=> false,	# Whether required or not
				'columns'				=> 30,		# Number of columns (optional; defaults to 30)
				'rows'					=> 5,		# Number of rows (optional; defaults to 30)
				'initialValue'			=> '',		# Default value (optional)
				'regexp'				=> '',		# Regular expression against which the submission must validate (optional)
		));
		
		# Ensure that arguments with a non-null default value are set (throwing an error if not), or assign the default value if none is specified
		foreach ($argumentsSpecification as $fieldType => $availableArguments) {
			foreach ($availableArguments as $argument => $defaultValue) {
				if (is_null ($defaultValue)) {
					if (!isSet ($arguments[$argument])) {
						$this->formSetupErrors['absent' . ucfirst ($fieldType) . ucfirst ($argument)] = "No '$argument' has been set for a specified $fieldType field.";
						${$argument} = $fieldType;
					} else {
						${$argument} = $arguments[$argument];
					}
				} else {
					${$argument} = (isSet ($arguments[$argument]) ? $arguments[$argument] : $defaultValue);
				}
			}
		}
		
		# Check whether the element name already been used accidentally (i.e. two fields with the same name) and if so add it to the array of duplicated element names
		if (isSet ($this->elements[$elementName])) {$this->duplicatedElementNames[] = $elementName;}
		
		# Make sure the element is not empty
		if (!isSet ($this->form[$elementName])) {$this->form[$elementName] = '';}
		
		# Handle whitespace issues
		#!# Policy issue of whether this should apply on a per-line basis
		$this->form[$elementName] = $this->handleWhiteSpace ($this->form[$elementName]);
		
		# Apply a regular expression check if required, against which each line (not the total input) must validate
		if ($regexp != '') {
			if (!empty ($this->form[$elementName])) {
				
				# Branch a copy of the data as an array, split by the newline and check it is complete
				$data = split ("\n", $this->form[$elementName]);
				
				# Split each line into two fields and loop through each line to deal with a mid-line split
				$totalLines = count ($data);
				for ($line = 0; $line < $totalLines; $line++) {
					
					# If the line does not validate against the regexp, add the line to a list of lines containing a problem
					if (!ereg ($regexp, rtrim ($data[$line], "\n\r"))) {
						$problemLines[] = ($line + 1);
					}
				}
				
				# If any problem lines are found, construct the error message for this
				if (isSet ($problemLines)) {
					$elementProblems['failsRegexp'] = (count ($problemLines) > 1 ? 'Rows ' : 'Row ') . implode (', ', $problemLines) . (count ($problemLines) > 1 ? ' do not' : ' does not') . ' match a specified pattern required for this section.';
				}
			}
		}
		
		# Check whether the field satisfies any requirement for a field to be required
		$requiredButEmpty = (($required) && ($this->form[$elementName] == ''));
		
		# Assign the initial value if the form is not posted (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid)
		if (!$this->formPosted) {$this->form[$elementName] = $initialValue;}
		
		# Define the widget's core HTML
		$widgetHtml = '<textarea name="' . $this->formName . "[$elementName]\" id=\"" . $this->formName . "[$elementName]\" cols=\"$columns\" rows=\"$rows\">" . htmlentities ($this->form[$elementName]) . '</textarea>';
		
		# Add the widget to the master array for eventual processing
		$this->elements[$elementName] = array (
			'type' => 'textarea',
			'html' => $widgetHtml,
			'title' => $title,
			'description' => $elementDescription,
			'restriction' => (isSet ($restriction) ? $restriction : false),
			'problems' => (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $required,
			'requiredButEmpty' => $requiredButEmpty,
			'suitableAsEmailTarget' => false,
			'outputFormat' => $outputFormat,
		);
	}
	
	
	/**
	 * Create a rich text editor field based on FCKeditor v.2 RC3 - www.fckeditor.net
	 * @param array $arguments Supplied arguments - see template
	 */
	 
	/*
	
	The following source code alterations must be made to FCKeditor 2.0 FC (released 10/5/05)
	
	1. Customised configurations which cannot go in the PHP at present
	Add the supplied file /_fckeditor/fckconfig-customised.js
	
	2. Customisations to the file manager connector configuration file
	Add the supplied file /_fckeditor/editor/filemanager/browser/mcpuk/connectors/php/config.php
	
	3. Search&replace: Remove all instances of the resource type location
	a. Remove /$test/   (which is in Commands/*.php)
	b. Remove /$resType (which is in FileUpload.php)
	
	*/
	
	function richtext ($arguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentsSpecification = array (
			'richtext' => array (
				'elementName'			=> NULL,	# Name of the element
				'title'					=> NULL,		# Introductory text
				'elementDescription'	=> '',		# Description text
				'outputFormat'			=> array (),# Presentation format (CURRENTLY UNDOCUMENTED)
				'required'				=> false,	# Whether required or not
				'width'					=> '600px',		# Width
				'height'				=> '400px',		# Height
				'editorConfig'				=> array (	# Editor configuration
					'CustomConfigurationsPath' => '/_fckeditor/fckconfig-customised.js',
					'FontFormats'			=> 'p;h1;h2;h3;h4;h5;h6;pre',
					'UserFilesPath'			=> '/',
					'EditorAreaCSS'			=> '',
					'BaseHref'				=> '',
					#'FormatIndentator'		=> "\t",
					'GeckoUseSPAN'			=> false,	#!# Even in .js version this seems to have no effect
					'StartupFocus'			=> false,
					'ToolbarCanCollapse'	=> false,
/*
					# Trying the syntax at http://www.heygrady.com/tutorials/example-2b2.php.txt :
					'ToolbarSets' => array ( 'NewToolbar' => array (
						array( 'Source' ),
						array( 'Cut','Copy','Paste','PasteText','-','Print' ),
						array( 'Undo','Redo','-','SelectAll','RemoveFormat' ),
						array( 'Bold','Italic','Underline','StrikeThrough','-','Subscript','Superscript' ),
						array( 'OrderedList','UnorderedList','-','Outdent','Indent' ),
						array( 'Link','Unlink' ),
						array( 'Image','Table','Rule','SpecialChar' ),
						array( 'FontFormat','-','TextColor' ),
						array( 'About' ),
						)),
*/

#					"ToolbarSets['pureContent']" => "[
#							['Source'],
#							['Cut','Copy','Paste','PasteText','PasteWord','-'/*,'SpellCheck'*/],
#							['Undo','Redo',/*'-','Find','Replace',*/'-','SelectAll','RemoveFormat'],
#							['Bold','Italic','StrikeThrough','-','Subscript','Superscript'],
#							['OrderedList','UnorderedList','-'],
#							['Link','Unlink'/*,'Anchor'*/],
#							['Image','Table','Rule','SpecialChar'/*,'UniversalKey'*/],
#							/*['Form','Checkbox','Radio','Input','Textarea','Select','Button','ImageButton','Hidden']*/
#							/*['ShowTableBorders','ShowDetails','-','Zoom'],*/
#							[/*'FontStyleAdv','-','FontStyle','-',*/'FontFormat','-','-']
#						];",

/*
					'LinkBrowserURL'		=> '/_fckeditor/editor/filemanager/browser/mcpuk/browser.html?Connector=connectors/php/connector.php',
					'ImageBrowserURL'		=> '/_fckeditor/editor/filemanager/browser/mcpuk/browser.html?Type=Image&Connector=connectors/php/connector.php',
*/
					'LinkBrowserURL'		=> '/_fckeditor/editor/filemanager/browser/default/browser.html?Connector=connectors/php/connector.php',
					'ImageBrowserURL'		=> '/_fckeditor/editor/filemanager/browser/default/browser.html?Type=Image&Connector=connectors/php/connector.php',
				),
				'initialValue'			=> '',		# Default value (optional)
				'editorBasePath'		=> '/_fckeditor/',	# Location of the editor files
				'editorToolbarSet'		=> 'pureContent',	# Editor toolbar set
		));
		
		# Ensure that arguments with a non-null default value are set (throwing an error if not), or assign the default value if none is specified
		foreach ($argumentsSpecification as $fieldType => $availableArguments) {
			foreach ($availableArguments as $argument => $defaultValue) {
				if (is_null ($defaultValue)) {
					if (!isSet ($arguments[$argument])) {
						$this->formSetupErrors['absent' . ucfirst ($fieldType) . ucfirst ($argument)] = "No '$argument' has been set for a specified $fieldType field.";
						${$argument} = $fieldType;
					} else {
						${$argument} = $arguments[$argument];
					}
					
				# Deal with subarguments for the config section
				} elseif ($argument == 'editorConfig') {
					foreach ($defaultValue as $subArgument => $subDefaultValue) {
						if (is_null ($subDefaultValue)) {
							if (!isSet ($arguments[$argument][$subArgument])) {
								$this->formSetupErrors['absent' . ucfirst ($fieldType) . ucfirst ($argument) . ucfirst ($subArgument)] = "No '$subArgument' has been set for a specified $argument argument in a specified $fieldType field.";
								${$argument}[$subArgument] = $fieldType;
							} else {
								${$argument}[$subArgument] = $arguments[$argument][$subArgument];
							}
						} else {
							${$argument}[$subArgument] = (isSet ($arguments[$argument][$subArgument]) ? $arguments[$argument][$subArgument] : $subDefaultValue);
						}
					}
					
				} else {
					${$argument} = (isSet ($arguments[$argument]) ? $arguments[$argument] : $defaultValue);
				}
			}
		}
		
		# Check whether the element name already been used accidentally (i.e. two fields with the same name) and if so add it to the array of duplicated element names
		if (isSet ($this->elements[$elementName])) {$this->duplicatedElementNames[] = $elementName;}
		
		# Make sure the element is not empty
		if (!isSet ($this->form[$elementName])) {$this->form[$elementName] = '';}
		
		# Handle whitespace issues
		#!# Policy issue of whether this should apply on a per-line basis
		$this->form[$elementName] = $this->handleWhiteSpace ($this->form[$elementName]);
		
		# Check whether the field satisfies any requirement for a field to be required
		$requiredButEmpty = (($required) && ($this->form[$elementName] == ''));
		
		# Assign the initial value if the form is not posted (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid)
		if (!$this->formPosted) {$this->form[$elementName] = $initialValue;}
		
		# Define the widget's core HTML by instantiating the richtext editor module and setting required options
		require_once ('fckeditor.php');
		$editor = new FCKeditor ($this->formName . "[$elementName]");
		$editor->BasePath	= $editorBasePath;
		$editor->Width		= $width;
		$editor->Height		= $height;
		$editor->ToolbarSet	= $editorToolbarSet;
		$editor->Value		= $this->form[$elementName];
		$editor->Config		= $editorConfig;
		$widgetHtml = $editor->CreateHtml ();
		
		# Add the widget to the master array for eventual processing
		$this->elements[$elementName] = array (
			'type' => 'richtext',
			'html' => $widgetHtml,
			'title' => $title,
			'description' => $elementDescription,
			'restriction' => (isSet ($restriction) ? $restriction : false),
			'problems' => (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $required,
			'requiredButEmpty' => $requiredButEmpty,
			'suitableAsEmailTarget' => false,
			'outputFormat' => $outputFormat,
		);
	}
	
	
	# Function to clean the content
	#!# More tidying needed
	function richtextClean ($content)
	{
		# Set options, as at http://tidy.sourceforge.net/docs/quickref.html
		$parameters = array (
			'output-xhtml' => true,
			'show-body-only'	=> true,
			'clean' => true,
			'enclose-text'	=> true,
			'drop-proprietary-attributes' => true,
			'drop-font-tags' => true,
			'drop-empty-paras' => true,
			'hide-comments' => true,
			'join-classes' => true,
			'join-styles' => true,
			'logical-emphasis' => true,
			'merge-divs'	=> true,
			'word-2000'	=> true,
			'indent'	=> false,
			'indent-spaces'	=> 4,
			'wrap'	=> 0,
			'fix-backslash'	=> false,
			'force-output'	=> true,
			'bare'	=> true,
		);
		
		# If the tidy extension is not available (e.g. PHP4), return the content directly
		if (!function_exists ('tidy_parse_string')) {return $content;}
		
		# Tidy up the output; see http://www.zend.com/php5/articles/php5-tidy.php for a tutorial
		$content = tidy_parse_string ($content, $parameters);
		tidy_clean_repair ($content);
		$content = tidy_get_output ($content);
		
		# Strip certain tags
		$stripTags = array ('span');
		foreach ($stripTags as $tag) {
			$contents = preg_replace ("/<\/?" . $tag . "(.|\s)*?>/", '', $content);
		}
		
		# Define further regexp replacements
		$manualRegexpReplacements = array (
			'<?xml:namespace([^>]*)>' => '',
		);
		$content = ereg_replace ('<\?xml:namespace([^>]*)>', '', $content);
		
		# Define further replacements
		$manualStringReplacements = array (
			# Cleanliness formatting
			'<h'	=> "\n<h",
			'</h1>'	=> "</h1>\n",
			'</h2>'	=> "</h2>\n",
			'</h3>'	=> "</h3>\n",
			'</h4>'	=> "</h4>\n",
			'</h5>'	=> "</h5>\n",
			'</h6>'	=> "</h6>\n",
			
			# WordHTML characters
			'<o:p> </o:p>'	=> '',
		);
		$content = str_replace (array_keys ($manualStringReplacements), array_values ($manualStringReplacements), $content);
		
		# Return the tidied content
		return $content;
	}
	
	
	/**
	 * Create a textarea with multiple lines that are split into an array of values
	 * @param array $arguments Supplied arguments - see template
	 */
	function textareaMultipleY ($arguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentsSpecification = array (
			'textareaMultipleY' => array (
				'elementName'			=> NULL,	# Name of the element
				'title'					=> '',		# Introductory text
				'elementDescription'	=> '',		# Description text
				'outputFormat'			=> array (),# Presentation format (CURRENTLY UNDOCUMENTED)
				'required'				=> false,	# Whether required or not
				'enforceNumeric'		=> false,	# Whether to enforce numeric input or not (optional; defaults to false)
				'columns'				=> 30,		# Number of columns (optional; defaults to 30)
				'rows'					=> 5,		# Number of rows (optional; defaults to 30)
				'initialValue'			=> '',		# Default value (optional)
				'regexp'				=> '',		# Regular expression against which the submission must validate (optional)
		));
		
		# Ensure that arguments with a non-null default value are set (throwing an error if not), or assign the default value if none is specified
		foreach ($argumentsSpecification as $fieldType => $availableArguments) {
			foreach ($availableArguments as $argument => $defaultValue) {
				if (is_null ($defaultValue)) {
					if (!isSet ($arguments[$argument])) {
						$this->formSetupErrors['absent' . ucfirst ($fieldType) . ucfirst ($argument)] = "No '$argument' has been set for a specified $fieldType field.";
						${$argument} = $fieldType;
					} else {
						${$argument} = $arguments[$argument];
					}
				} else {
					${$argument} = (isSet ($arguments[$argument]) ? $arguments[$argument] : $defaultValue);
				}
			}
		}
		
		# Check whether the element name already been used accidentally (i.e. two fields with the same name) and if so add it to the array of duplicated element names
		if (isSet ($this->elements[$elementName])) {$this->duplicatedElementNames[] = $elementName;}
		
		# Make sure the element is not empty
		if (!isSet ($this->form[$elementName])) {$this->form[$elementName] = '';}
		
		# Handle whitespace issues
		$this->form[$elementName] = $this->handleWhiteSpace ($this->form[$elementName]);
		
		# Clean up the submitted data if required to enforce numeric
		if ($enforceNumeric) {$this->form[$elementName] = $this->cleanToNumeric ($this->form[$elementName]);}
		
		# Apply a regular expression check if required, against which each line (not the total input) must validate
		#!# This should be divorced to a separate function (as with other areas of each widget, as it is duplicated)
		if ($regexp != '') {
			if (!empty ($this->form[$elementName])) {
				
				# Branch a copy of the data as an array, split by the newline and check it is complete
				$data = split ("\n", $this->form[$elementName]);
				
				# Split each line into two fields and loop through each line to deal with a mid-line split
				$totalLines = count ($data);
				for ($line = 0; $line < $totalLines; $line++) {
					
					# If the line does not validate against the regexp, add the line to a list of lines containing a problem
					if (!ereg ($regexp, rtrim ($data[$line], "\n\r"))) {
						$problemLines[] = ($line + 1);
					}
				}
				
				# If any problem lines are found, construct the error message for this
				if (isSet ($problemLines)) {
					$elementProblems['failsRegexp'] = (count ($problemLines) > 1 ? 'Rows ' : 'Row ') . implode (', ', $problemLines) . (count ($problemLines) > 1 ? ' do not' : ' does not') . ' match a specified pattern required for this section.';
				}
			}
		}
		
		# Check whether the field satisfies any requirement for a field to be required
		$requiredButEmpty = (($required) && ($this->form[$elementName] == ''));
		
		# Assign the initial value if the form is not posted (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid)
		if (!$this->formPosted) {$this->form[$elementName] = $initialValue;}
		
		# Describe restrictions on the widget
		$restriction = 'Must have one numeric item per line';
		
		# Define the widget's core HTML
		$widgetHtml = '<textarea name="' . $this->formName . "[$elementName]\" id=\"" . $this->formName . "[$elementName]\" cols=\"$columns\" rows=\"$rows\">" . htmlentities ($this->form[$elementName]) . '</textarea>';
		
		# Add the widget to the master array for eventual processing
		$this->elements[$elementName] = array (
			'type' => 'textareaMultipleY',
			'html' => $widgetHtml,
			'title' => $title,
			'description' => $elementDescription,
			'restriction' => (isSet ($restriction) ? $restriction : false),
			'problems' => (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $required,
			'requiredButEmpty' => $requiredButEmpty,
			'suitableAsEmailTarget' => false,
			'outputFormat' => $outputFormat,
		);
	}
	
	
	/**
	 * Create a textarea box with multiple lines of x and y co-ordinates only
	 * @todo Consider merging with generic textarea
	 * @param array $arguments Supplied arguments - see template
	 */
	function textareaMultipleXy ($arguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentsSpecification = array (
			'textareaMultipleXy' => array (
				'elementName'			=> NULL,	# Name of the element
				'title'					=> '',		# Introductory text
				'elementDescription'	=> '',		# Description text
				'outputFormat'			=> array (),# Presentation format (CURRENTLY UNDOCUMENTED)
				'required'				=> false,	# Whether required or not
				'enforceNumeric'		=> false,	# Whether to enforce numeric input or not (optional; defaults to false)
				'columns'				=> 30,		# Number of columns (optional; defaults to 30)
				'rows'					=> 5,		# Number of rows (optional; defaults to 30)
				'initialValue'			=> '',		# Default value (optional)
		));
		
		# Ensure that arguments with a non-null default value are set (throwing an error if not), or assign the default value if none is specified
		foreach ($argumentsSpecification as $fieldType => $availableArguments) {
			foreach ($availableArguments as $argument => $defaultValue) {
				if (is_null ($defaultValue)) {
					if (!isSet ($arguments[$argument])) {
						$this->formSetupErrors['absent' . ucfirst ($fieldType) . ucfirst ($argument)] = "No '$argument' has been set for a specified $fieldType field.";
						${$argument} = $fieldType;
					} else {
						${$argument} = $arguments[$argument];
					}
				} else {
					${$argument} = (isSet ($arguments[$argument]) ? $arguments[$argument] : $defaultValue);
				}
			}
		}
		
		# Check whether the element name already been used accidentally (i.e. two fields with the same name) and if so add it to the array of duplicated element names
		if (isSet ($this->elements[$elementName])) {$this->duplicatedElementNames[] = $elementName;}
		
		# Make sure the element is not empty
		if (!isSet ($this->form[$elementName])) {$this->form[$elementName] = '';}
		
		# Handle whitespace issues
		$this->form[$elementName] = $this->handleWhiteSpace ($this->form[$elementName]);
		
		# Clean up the submitted data if required to enforce numeric
		if ($enforceNumeric) {$this->form[$elementName] = $this->cleanToNumeric ($this->form[$elementName]);}
		
		# Do further processing if the data is not empty
		if (!empty ($this->form[$elementName])) {
			
			# Branch a copy of the data as an array, split by the newline and check it is complete
			$data = explode ("\n", $this->form[$elementName]);
			
			# Split each line into two fields and loop through each line to deal with a mid-line split
			$totalLines = count ($data);
			for ($line = 0; $line < $totalLines; $line++) {
				
				# If there are not at least two columns of data (i.e. that there's a space between), add the line to a list of lines containing a problem
				#!# Not convinced this is correct - what if cleanToNumeric is off?
				if (!preg_match ("/\s/i", $data[$line])) {	// strpos fails for whitespace
					$problemLines[] = ($line + 1);
				}
			}
			
			# If any problem lines are found, construct the error message for this
			if (isSet ($problemLines)) {
				$elementProblems['twoCoordinatesRequired'] = (count ($problemLines) > 1 ? 'Rows ' : 'Row ') . implode (', ', $problemLines) . (count ($problemLines) > 1 ? ' do not' : ' does not') . ' contain two co-ordinates per line.';
			}
		}
		
		# Check whether the field satisfies any requirement for a field to be required
		$requiredButEmpty = (($required) && ($this->form[$elementName] == ''));
		
		# Assign the initial value if the form is not posted (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid)
		if (!$this->formPosted) {$this->form[$elementName] = $initialValue;}
		
		# Describe restrictions on the widget
		$restriction = 'Must have two numeric items (x,y) per line';
		
		# Define the widget's core HTML
		$widgetHtml = '<textarea name="' . $this->formName . "[$elementName]\" id=\"" . $this->formName . "[$elementName]\" cols=\"$columns\" rows=\"$rows\">" . htmlentities ($this->form[$elementName]) . '</textarea>';
		
		# Add the widget to the master array for eventual processing
		$this->elements[$elementName] = array (
			'type' => 'textareaMultipleXy',
			'html' => $widgetHtml,
			'title' => $title,
			'description' => $elementDescription,
			'restriction' => (isSet ($restriction) ? $restriction : false),
			'problems' => (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $required,
			'requiredButEmpty' => $requiredButEmpty,
			'suitableAsEmailTarget' => false,
			'outputFormat' => $outputFormat,
		);
	}
	
	
	/**
	 * Create a select (drop-down) box widget
	 * @param array $arguments Supplied arguments - see template
	 */
	function select ($arguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentsSpecification = array (
			'select' => array (
				'elementName'			=> NULL,	# Name of the element
				'valuesArray'			=> array (),# Simple array of selectable values
				'title'					=> '',		# Introductory text
				'elementDescription'	=> '',		# Description text
				'outputFormat'			=> array (),# Presentation format (CURRENTLY UNDOCUMENTED)
				'multiple'				=> false,	# Whether to create a multiple-mode select box
				'minimumRequired'		=> 0,		# The minimum number which must be selected (defaults to 0)
				'visibleSize'			=> 1,		# Number of rows (optional; defaults to 1)
				'initialValues'			=> array (),# Pre-selected item(s)
				'forceAssociative'		=> false,	# Force the supplied array of values to be associative
		));
		
		# Ensure that arguments with a non-null default value are set (throwing an error if not), or assign the default value if none is specified
		foreach ($argumentsSpecification as $fieldType => $availableArguments) {
			foreach ($availableArguments as $argument => $defaultValue) {
				if (is_null ($defaultValue)) {
					if (!isSet ($arguments[$argument])) {
						$this->formSetupErrors['absent' . ucfirst ($fieldType) . ucfirst ($argument)] = "No '$argument' has been set for a specified $fieldType field.";
						${$argument} = $fieldType;
					} else {
						${$argument} = $arguments[$argument];
					}
				} else {
					${$argument} = (isSet ($arguments[$argument]) ? $arguments[$argument] : $defaultValue);
				}
			}
		}
		
		# Check whether the element name already been used accidentally (i.e. two fields with the same name) and if so add it to the array of duplicated element names
		if (isSet ($this->elements[$elementName])) {$this->duplicatedElementNames[] = $elementName;}
		
		# Make sure the element is not empty
		if (!isSet ($this->form[$elementName])) {$this->form[$elementName] = array ();}
		
		# Check that valuesArray is not empty
		#!# Only run other checks below if this error isn't thrown
		if (empty ($valuesArray)) {$this->formSetupErrors['selectNoValues'] = 'No values have been set as selection items.';}
		
		# Check that the given minimumRequired is not more than the number of items actually available
		$totalSubItems = count ($valuesArray);
		if ($minimumRequired > $totalSubItems) {$this->formSetupErrors['selectMinimumMismatch'] = "The required minimum number of items which must be selected (<strong>$minimumRequired</strong>) specified is above the number of select items actually available (<strong>$totalSubItems</strong>).";}
		
		# Ensure the initial value(s) is an array, even if only an empty one, converting if necessary
		$initialValues = application::ensureArray ($initialValues);
		
		# Ensure that there cannot be multiple initial values set if the multiple flag is off
		$totalInitialValues = count ($initialValues);
		if ((!$multiple) && ($totalInitialValues > 1)) {
			$this->formSetupErrors['initialValuesTooMany'] = "In the <strong>$elementName</strong> element, $totalInitialValues total initial values were assigned but the form has been set up to allow only one item to be selected by the user.";
		}
		
		# Check whether the array is an associative array
		$valuesAreAssociativeArray = (application::isAssociativeArray ($valuesArray) || $forceAssociative);
		$submittableValues = ($valuesAreAssociativeArray ? array_keys ($valuesArray) : array_values ($valuesArray));
		
		# Special syntax to set the value of a URL-supplied GET value as the initial value; if the supplied item is not present, ignore it; otherwise replace the initialValues array with the single selected item
		#!# Way of applying more than one item?
		#!# Apply this to checkboxes and radio buttons also
		#!# Need to make 'url:$' in the values array not allowable as a genuine option
		if (isSet ($initialValues[0])) {
			$identifier = 'url:$';
			if (substr ($initialValues[0], 0, strlen ($identifier)) == $identifier) {
				$urlArgumentKey = substr ($initialValues[0], strlen ($identifier));
				$initialValues = array (application::urlSuppliedValue ($urlArgumentKey, $submittableValues));
			} else {
				
				# Ensure that all initial values are in the valuesArray
				foreach ($initialValues as $initialValue) {
					if (!in_array ($initialValue, $submittableValues)) {
						$missingValues[] = $initialValue;
					}
				}
				if (isSet ($missingValues)) {
					$totalMissingValues = count ($missingValues);
					$this->formSetupErrors['initialValuesMissingFromValuesArray'] = "In the <strong>$elementName</strong> element, the initial " . ($totalMissingValues > 1 ? 'values ' : 'value ') . implode (', ', $missingValues) . ($totalMissingValues > 1 ? ' were' : ' was') . ' not found in the list of available items for selection by the user.';
				}
			}
		}
		
		# Ensure that the 'null' text does not clash with any items in the valuesArray
		if (in_array ($this->nullText, $submittableValues)) {
			$this->formSetupErrors['initialValuesNullClash'] = "In the <strong>$elementName</strong> element, the null text ('" . $this->nullText . "') clashes with one of list of available items for selection by the user. One or the other must be changed.";
		}
		
		# Clear the null text if it appears, or empty submissions
		#!# Need to modify this as the null text should never be a submitted value now
		foreach ($this->form[$elementName] as $key => $value) {
			#!# Is the empty ($value) check being done for other similar elements to prevent empty submissions?
			if (($this->form[$elementName][$key] == $this->nullText) || (empty ($value))) {
				unset ($this->form[$elementName][$key]);
				break;
			}
		}
		
		# Emulate the need for the field to be 'required', i.e. the minimum number of fields is greater than 0
		$required = ($minimumRequired > 0);
		
		# Produce a problem message if the number submitted is fewer than the number required
		$totalSubmitted = count ($this->form[$elementName]);
		if (($totalSubmitted != 0) && ($totalSubmitted < $minimumRequired)) {
			$elementProblems['insufficientSelected'] = ($minimumRequired != $totalSubItems ? 'At least' : 'All') . " <strong>$minimumRequired</strong> " . ($minimumRequired > 1 ? 'items' : 'item') . ' must be selected.';
		}
		
		# Prevent multiple submissions when not in multiple mode
		if (!$multiple && ($totalSubmitted > 1)) {$elementProblems['multipleSubmissionsDisallowed'] = 'More than one item was submitted but only one is acceptable';}
		
		# If nothing has been submitted mark it as required but empty
		$requiredButEmpty = (($required) && ($totalSubmitted == 0));
		
		# Assign the initial values if the form is not posted (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid)
		if (!$this->formPosted) {$this->form[$elementName] = $initialValues;}
		
		# Describe restrictions on the widget
		if ($multiple) {
			$restriction = (($minimumRequired > 1) ? "Minimum $minimumRequired required; use Control/Shift" : 'Use Control/Shift for multiple');
		}
		
		# Determine whether this field is suitable as the target for an e-mail and, if so, whether a suffix is required
		#R# This can become ternary, or make $multiple / $minimumRequired as arguments to suitableAsEmailTarget
		$suitableAsEmailTarget = false;
		#!# Apply this to checkboxes
		if ((!$multiple) && ($minimumRequired == 1)) {
			$suitableAsEmailTarget = $this->suitableAsEmailTarget ($submittableValues);
		}
		
		# Define the widget's core HTML
		$widgetHtml = "\n\t\t\t<select name=\"" . $this->formName . "[$elementName][]\"" . (($multiple) ? ' multiple="multiple"' : '') . " size=\"$visibleSize\">";
		#!# Does this now mean that a check for submissions of $this->nullText is no longer required, as the value will be "" ?
		$widgetHtml .= "\n\t\t\t\t" . '<option value="">' . $this->nullText . '</option>';
		foreach ($valuesArray as $key => $value) {
			$widgetHtml .= "\n\t\t\t\t" . '<option value="' . htmlentities (($valuesAreAssociativeArray ? $key : $value)) . '"' . (in_array (($valuesAreAssociativeArray ? $key : $value), $this->form[$elementName]) ? ' selected="selected"' : '') . '>' . htmlentities ($value) . '</option>';
		}
		$widgetHtml .= "\n\t\t\t</select>\n\t\t";
		
		# Add the widget to the master array for eventual processing
		$this->elements[$elementName] = array (
			'type' => 'select',
			'html' => $widgetHtml,
			'title' => $title,
			'description' => $elementDescription,
			'restriction' => (isSet ($restriction) ? $restriction : false),
			'problems' => (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $required,
			'requiredButEmpty' => $requiredButEmpty,
			'valuesArray' => $valuesArray,
			'suitableAsEmailTarget' => $suitableAsEmailTarget,
			'outputFormat' => $outputFormat,
		);
	}
	
	
	/**
	 * Create a radio-button widget set
	 * @param array $arguments Supplied arguments - see template
	 */
	function radiobuttons ($arguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentsSpecification = array (
			'radiobuttons' => array (
				'elementName'			=> NULL,	# Name of the element
				'valuesArray'			=> array (),# Simple array of selectable values
				'title'					=> '',		# Introductory text
				'elementDescription'	=> '',		# Description text
				'outputFormat'			=> array (),# Presentation format (CURRENTLY UNDOCUMENTED)
				'required'				=> false,	# Whether required or not
				'initialValue'			=> array (),# Pre-selected item
		));
		
		# Ensure that arguments with a non-null default value are set (throwing an error if not), or assign the default value if none is specified
		foreach ($argumentsSpecification as $fieldType => $availableArguments) {
			foreach ($availableArguments as $argument => $defaultValue) {
				if (is_null ($defaultValue)) {
					if (!isSet ($arguments[$argument])) {
						$this->formSetupErrors['absent' . ucfirst ($fieldType) . ucfirst ($argument)] = "No '$argument' has been set for a specified $fieldType field.";
						${$argument} = $fieldType;
					} else {
						${$argument} = $arguments[$argument];
					}
				} else {
					${$argument} = (isSet ($arguments[$argument]) ? $arguments[$argument] : $defaultValue);
				}
			}
		}
		
		# Check whether the element name already been used accidentally (i.e. two fields with the same name) and if so add it to the array of duplicated element names
		if (isSet ($this->elements[$elementName])) {$this->duplicatedElementNames[] = $elementName;}
		
		# Check that valuesArray is not empty
		if (empty ($valuesArray)) {$this->formSetupErrors['radiobuttonsNoValues'] = 'No values have been set for the set of radio buttons.';}
		
		# Make sure the element is not empty
		if (!isSet ($this->form[$elementName])) {$this->form[$elementName] = '';}
		
		# Check whether the array is an associative array
		$valuesAreAssociativeArray = application::isAssociativeArray ($valuesArray);
		$submittableValues = ($valuesAreAssociativeArray ? array_keys ($valuesArray) : array_values ($valuesArray));
		
		# Ensure that the initial value, if one is set, is in the valuesArray
		#!# Should the initial value being set be the 'real' or visible value when using an associative array?
		if ((!empty ($initialValue)) && (!in_array ($initialValue, $submittableValues))) {
			$this->formSetupErrors['initialValuesMissingFromValuesArray'] = "In the <strong>$elementName</strong> element, the initial value was not found in the list of available items for selection by the user.";
		}
		
		# Ensure that the 'null' text does not clash with any items in the valuesArray
		if (in_array ($this->nullText, $valuesArray)) {
			$this->formSetupErrors['initialValuesNullClash'] = "In the <strong>$elementName</strong> element, the null text ('" . $this->nullText . "') clashes with one of list of available items for selection by the user. One or the other must be changed.";
		}
		
		# Check whether the field satisfies any requirement for a field to be required
		$requiredButEmpty = (($required) && ($this->form[$elementName] == ''));
		
		# Assign the initial value if the form is not posted (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid)
		if (!$this->formPosted) {$this->form[$elementName] = $initialValue;}
		
		# If it's not a required field, add a null field here
		if (!$required) {array_unshift ($valuesArray, $this->nullText);}
		
		# Define the widget's core HTML
		$widgetHtml = '';
		foreach ($valuesArray as $key => $value) {
			$elementId = ereg_replace (' ', '_', $elementName . '_' . $value);
			$submittableValue = ($valuesAreAssociativeArray ? $key : $value);
			$widgetHtml .= "\n\t\t\t" . '<input type="radio" name="' . $this->formName . "[$elementName]\"" . ' value="' . htmlentities ($submittableValue) . '"' . (($submittableValue == $this->form[$elementName]) ? ' checked="checked"' : '') . ' id="' . $elementId . '"' . " /><label for=\"" . $elementId . '">' . htmlentities ($value) . "</label><br />";
		}
		$widgetHtml .= "\n\t\t";
		
		# Clear a null submission (this must come after the HTML is defined)
		if ($this->form[$elementName] == $this->nullText) {$this->form[$elementName] = '';}
		
		# Add the widget to the master array for eventual processing
		$this->elements[$elementName] = array (
			'type' => 'radiobuttons',
			'html' => $widgetHtml,
			'title' => $title,
			'description' => $elementDescription,
			'restriction' => (isSet ($restriction) ? $restriction : false),
			'problems' => (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $required,
			'requiredButEmpty' => $requiredButEmpty,
			'valuesArray' => $valuesArray,
			'suitableAsEmailTarget' => $required,
			'outputFormat' => $outputFormat,
		);
	}
	
	
	/**
	 * Create a checkbox(es) widget set
	 * @param array $arguments Supplied arguments - see template
	 */
	function checkboxes ($arguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentsSpecification = array (
			'checkboxes' => array (
				'elementName'			=> NULL,	# Name of the element
				'valuesArray'			=> array (),# Simple array of selectable values
				'title'					=> '',		# Introductory text
				'elementDescription'	=> '',		# Description text
				'outputFormat'			=> array (),# Presentation format (CURRENTLY UNDOCUMENTED)
				'minimumRequired'		=> 0,		# The minimum number which must be selected (defaults to 0)
#!# Hack!
				'maximumRequired'		=> 999,		# The maximum number which must be selected (defaults to 0)
				'initialValues'			=> array (),# Pre-selected item(s)
		));
		
		# Ensure that arguments with a non-null default value are set (throwing an error if not), or assign the default value if none is specified
		foreach ($argumentsSpecification as $fieldType => $availableArguments) {
			foreach ($availableArguments as $argument => $defaultValue) {
				if (is_null ($defaultValue)) {
					if (!isSet ($arguments[$argument])) {
						$this->formSetupErrors['absent' . ucfirst ($fieldType) . ucfirst ($argument)] = "No '$argument' has been set for a specified $fieldType field.";
						${$argument} = $fieldType;
					} else {
						${$argument} = $arguments[$argument];
					}
				} else {
					${$argument} = (isSet ($arguments[$argument]) ? $arguments[$argument] : $defaultValue);
				}
			}
		}
		
		# Check whether the element name already been used accidentally (i.e. two fields with the same name) and if so add it to the array of duplicated element names
		if (isSet ($this->elements[$elementName])) {$this->duplicatedElementNames[] = $elementName;}
		
		# Make sure the element is not empty; NB the [] is required to prevent Uninitialized string offsets at the stickynessHtml creation point - basically the isSet would otherwise fail because of checking an array key existing for a non-array element
		if (!isSet ($this->form[$elementName])) {$this->form[$elementName][] = '';}
		
		# Check that valuesArray is not empty
		if (empty ($valuesArray)) {$this->formSetupErrors['checkboxesNoValues'] = 'No values have been set for the set of checkboxes.';}
		
		# Check that the given minimumRequired is not more than the number of checkboxes actually available
		$totalSubItems = count ($valuesArray);
		if ($minimumRequired > $totalSubItems) {$this->formSetupErrors['checkboxesMinimumMismatch'] = "The required minimum number of checkboxes (<strong>$minimumRequired</strong>) specified is above the number of checkboxes actually available (<strong>$totalSubItems</strong>).";}
		
		# Check whether the array is an associative array
		$valuesAreAssociativeArray = application::isAssociativeArray ($valuesArray);
		$submittableValues = ($valuesAreAssociativeArray ? array_keys ($valuesArray) : array_values ($valuesArray));
		
		# Ensure that all initial values are in the valuesArray
		$initialValues = application::ensureArray ($initialValues);
		foreach ($initialValues as $initialValue) {
			if (!in_array ($initialValue, $submittableValues)) {
				$missingValues[] = $initialValue;
			}
		}
		if (isSet ($missingValues)) {
			$totalMissingValues = count ($missingValues);
			$this->formSetupErrors['initialValuesMissingFromValuesArray'] = "In the <strong>$elementName</strong> element, the initial " . ($totalMissingValues > 1 ? 'values ' : 'value ') . implode (', ', $missingValues) . ($totalMissingValues > 1 ? ' were' : ' was') . ' not found in the list of available items for selection by the user.';
		}
		
		# Start a tally to check the number of checkboxes checked
		$checkedTally = 0;
		
		# Loop through each element subname and construct HTML
		$widgetHtml = '';
		foreach ($valuesArray as $key => $value) {
			
			# Assign the submittable value
			$submittableValue = ($valuesAreAssociativeArray ? $key : $value);
			
			# Define the element ID, which must be unique	
			#!# This needs to deal with encoding, so that all id="" names are valid XHTML - e.g. ! will result in an invalid item
			$elementId = str_replace (' ', '_', ($this->formName . '__' . $elementName . '__' . $submittableValue));
			
			# Assign the initial value if the form is not posted (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid)
			if (!$this->formPosted) {
				if (in_array ($submittableValue, $initialValues)) {
					$this->form[$elementName][$submittableValue] = true;
				}
			}
			
			# Apply stickyness to each checkbox if necessary
			$stickynessHtml = '';
			if (isSet ($this->form[$elementName][$submittableValue])) {
				if ($this->form[$elementName][$submittableValue]) {
					$stickynessHtml = ' checked="checked"';
					
					# Tally the number of items checked
					$checkedTally++;
				}
			} else {
				# Ensure every element is defined (even if empty), so that the case of writing to a file doesn't go wrong
				$this->form[$elementName][$submittableValue] = '';
			}
			
			# Create the HTML; note that spaces (used to enable the 'label' attribute for accessibility reasons) in the ID will be replaced by an underscore (in order to remain valid XHTML)
			$widgetHtml .= "\n\t\t\t" . '<input type="checkbox" name="' . $this->formName . "[$elementName][$submittableValue]" . '" id="' . $elementId . '" value="true"' . $stickynessHtml . ' /><label for="' . $elementId . '">' . $value . "</label><br />";
		}
		
		# Make sure the number of checkboxes given is above the $minimumRequired
		if ($checkedTally < $minimumRequired) {
			$elementProblems['insufficientSelected'] = "A minimum of $minimumRequired checkboxes are required to be selected.";
		}
		
		# Make sure the number of checkboxes given is above the $maximumRequired
		#!# Hacked in quickly on 041103 - needs regression testing
		if ($checkedTally > $maximumRequired) {
			$elementProblems['tooManySelected'] = "A maximum of $maximumRequired checkboxes are required to be selected.";
		}
		
		# Describe restrictions on the widget
		if ($minimumRequired > 1) {$restriction = "Minimum $minimumRequired items required";}
		
		# Add the widget to the master array for eventual processing
		$this->elements[$elementName] = array (
			'type' => 'checkboxes',
			'html' => $widgetHtml,
			'title' => $title,
			'description' => $elementDescription,
			'restriction' => (isSet ($restriction) ? $restriction : false),
			'problems' => (isSet ($elementProblems) ? $elementProblems : false),
			'required' => false,
			'requiredButEmpty' => false, # This is covered by $elementProblems
			'valuesArray' => $valuesArray,
			'suitableAsEmailTarget' => false,
			'outputFormat' => $outputFormat,
		);
	}
	
	
	/**
	 * Create a date/datetime widget set
	 * @param array $arguments Supplied arguments - see template
	 */
	function datetime ($arguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentsSpecification = array (
			'datetime' => array (
				'elementName'			=> NULL,	# Name of the element
				'title'					=> '',		# Introductory text
				'elementDescription'	=> '',		# Description text
				'outputFormat'			=> array (),# Presentation format (CURRENTLY UNDOCUMENTED)
				'required'				=> false,	# Whether required or not
				'level'					=> 'date',	# Whether to show a 'datetime' or just 'date' widget set
				'initialValue'			=> '',		# Initial value - either 'timestamp' or an SQL string
		));
		
		# Ensure that arguments with a non-null default value are set (throwing an error if not), or assign the default value if none is specified
		foreach ($argumentsSpecification as $fieldType => $availableArguments) {
			foreach ($availableArguments as $argument => $defaultValue) {
				if (is_null ($defaultValue)) {
					if (!isSet ($arguments[$argument])) {
						$this->formSetupErrors['absent' . ucfirst ($fieldType) . ucfirst ($argument)] = "No '$argument' has been set for a specified $fieldType field.";
						${$argument} = $fieldType;
					} else {
						${$argument} = $arguments[$argument];
					}
				} else {
					${$argument} = (isSet ($arguments[$argument]) ? $arguments[$argument] : $defaultValue);
				}
			}
		}
		
		# Check whether the element name already been used accidentally (i.e. two fields with the same name) and if so add it to the array of duplicated element names
		if (isSet ($this->elements[$elementName])) {$this->duplicatedElementNames[] = $elementName;}
		
		# Make sure the element is not empty (ensure that a full date and time array exists to prevent undefined offsets in case an incomplete set has been posted)
		if (!isSet ($this->form[$elementName])) {$this->form[$elementName] = array ('year' => '', 'month' => '', 'day' => '', 'time' => '');}
		
		# Start a flag later used for checking whether all fields are empty against the requirement that a field should be completed
		$requiredButEmpty = false;
		
		# Assign the initial value if the form is not posted (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid)
		if (!$this->formPosted) {
			$this->form[$elementName] = datetime::getDateTimeArray ($initialValue);
		} else {
 			
			# Check whether all fields are empty, starting with assuming all fields are not incomplete
			$allFieldsIncomplete = false;
			if ($level == 'datetime') {
				if (($this->form[$elementName]['day'] == '') && ($this->form[$elementName]['month'] == '') && ($this->form[$elementName]['year'] == '') && ($this->form[$elementName]['time'] == '')) {$allFieldsIncomplete = true;}
			} else {
				if (($this->form[$elementName]['day'] == '') && ($this->form[$elementName]['month'] == '') && ($this->form[$elementName]['year'] == '')) {$allFieldsIncomplete = true;}
			}
			
			# If all fields are empty, and the widget is required, set that the field is required but empty
			if ($allFieldsIncomplete) {
				if ($required) {$requiredButEmpty = true;}
			} else {
				
				# Deal with month conversion by adding leading zeros as required
				if (($this->form[$elementName]['month'] > 0) && ($this->form[$elementName]['month'] <= 12)) {$this->form[$elementName]['month'] = sprintf ('%02s', $this->form[$elementName]['month']);}
				
				# If automatic conversion is set and the year is two characters long, convert the date to four years by adding 19 or 20 as appropriate
				if (($this->autoCenturyConversionEnabled) && (strlen ($this->form[$elementName]['year']) == 2)) {
					$this->form[$elementName]['year'] = (($this->form[$elementName]['year'] <= $this->autoCenturyConversionLastYear) ? '20' : '19') . $this->form[$elementName]['year'];
				}
				
				# Check that all parts have been completed
				if (($this->form[$elementName]['day'] == '') || ($this->form[$elementName]['month'] == '') || ($this->form[$elementName]['year'] == '') || (($level == 'datetime') && ($this->form[$elementName]['time'] == ''))) {
					$elementProblems['notAllComplete'] = "Not all parts have been completed!";
				} else {
					
					# Check that a valid month (01-12, corresponding to Jan-Dec respectively) has been submitted
					if ($this->form[$elementName]['month'] > 12) {
						$elementProblems['monthFieldInvalid'] = 'The month part is invalid!';
					}
					
					# Check that the day and year fields are numeric
					if ((!is_numeric ($this->form[$elementName]['day'])) && (!is_numeric ($this->form[$elementName]['year']))) {
						$elementProblems['dayYearFieldsNotNumeric'] = 'Both the day and year part must be numeric!';
					} else {
						
						# Check that the day is numeric
						if (!is_numeric ($this->form[$elementName]['day'])) {
							$elementProblems['dayFieldNotNumeric'] = 'The day part must be numeric!';
						}
						
						# Check that the year is numeric
						if (!is_numeric ($this->form[$elementName]['year'])) {
							$elementProblems['yearFieldNotNumeric'] = 'The year part must be numeric!';
							
						# If the year is numeric, ensure the year has been entered as a two or four digit amount
						} else {
							if ($this->autoCenturyConversionEnabled) {
								if ((strlen ($this->form[$elementName]['year']) != 2) && (strlen ($this->form[$elementName]['year']) != 4)) {
									$elementProblems['yearInvalid'] = 'The year part must be either two or four digits!';
								}
							} else {
								if (strlen ($this->form[$elementName]['year']) != 4) {
									$elementProblems['yearInvalid'] = 'The year part must be four digits!';
								}
							}
						}
					}
					
					# If all date parts have been entered correctly, check whether the date is valid
					if (!isSet ($elementProblems)) {
						if (!checkdate (($this->form[$elementName]['month']), $this->form[$elementName]['day'], $this->form[$elementName]['year'])) {
							$elementProblems['dateInvalid'] = 'An invalid date has been entered!';
						}
					}
				}
				
				# If the time is required in addition to the date, parse the time field, allowing flexible input syntax
				if ($level == 'datetime') {
					
					# Only do time processing if the time field isn't empty
					if (!empty ($this->form[$elementName]['time'])) {
						
						# If the time parsing passes, substitute the submitted version with the parsed and corrected version
						if ($time = datetime::parseTime ($this->form[$elementName]['time'])) {
							$this->form[$elementName]['time'] = $time;
						
						# If, instead, the time parsing fails, leave the original submitted version and add the problem to the errors array
						} else {
							$elementProblems['timePartInvalid'] = 'The time part is invalid!';
						}
					}
				}
			}
		}
		
		# Describe restrictions on the widget
		if ($level == 'datetime') {$restriction = 'Time can be entered flexibly';}
		
		# Start to define the widget's core HTML
		$widgetHtml = '';
		
		# Add in the time if required
		if ($level == 'datetime') {
			$widgetHtml .= "\n\t\t\t" . '<span class="' . (!isSet ($elementProblems['timePartInvalid']) ? 'comment' : 'warning') . '">t:&nbsp;</span><input name="' . $this->formName . '[' . $elementName . '][time]" type="text" size="10" value="' . $this->form[$elementName]['time'] . '" />';
		}
		
		# Define the date, month and year input boxes; if the day or year are 0 then nothing will be displayed
		$widgetHtml .= "\n\t\t\t" . '<span class="comment">d:&nbsp;</span><input name="' . $this->formName . '[' . $elementName . '][day]"  size="2" maxlength="2" value="' . (($this->form[$elementName]['day'] != '00') ? $this->form[$elementName]['day'] : '') . '" />&nbsp;';
		$widgetHtml .= "\n\t\t\t" . '<span class="comment">m:</span>';
		$widgetHtml .= "\n\t\t\t" . '<select name="' . $this->formName . '[' . $elementName . '][month]">';
		$months = array (1 => 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'June', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
		$widgetHtml .= "\n\t\t\t\t" . '<option value="">Select</option>';
		foreach ($months as $monthNumber => $monthName) {
			$widgetHtml .= "\n\t\t\t\t" . '<option value="' . sprintf ('%02s', $monthNumber) . '"' . (($this->form[$elementName]['month'] == sprintf ('%02s', $monthNumber)) ? ' selected="selected"' : '') . '>' . $monthName . '</option>';
		}
		$widgetHtml .= "\n\t\t\t" . '</select>';
		$widgetHtml .= "\n\t\t\t" . '<span class="comment">y:&nbsp;</span><input size="4" name="' . $this->formName . '[' . $elementName . '][year]" maxlength="4" value="' . (($this->form[$elementName]['year'] != '0000') ? $this->form[$elementName]['year'] : '') . '" />' . "\n\t\t";
		
		# Add the widget to the master array for eventual processing
		$this->elements[$elementName] = array (
			'type' => 'datetime',
			'html' => $widgetHtml,
			'title' => $title,
			'description' => $elementDescription,
			'restriction' => (isSet ($restriction) ? $restriction : false),
			'problems' => (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $required,
			'requiredButEmpty' => $requiredButEmpty,
			'level' => $level,
			'suitableAsEmailTarget' => false,
			'outputFormat' => $outputFormat,
		);
	}
	
	
	/**
	 * Create an upload widget set
	 * Note that, for security reasons, browsers do not support setting an initial value.
	 * @param array $arguments Supplied arguments - see template
	 */
	function upload ($arguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentsSpecification = array (
			'upload' => array (
				'elementName'			=> NULL,	# Name of the element
				'title'					=> '',		# Introductory text
				'elementDescription'	=> '',		# Description text
				'outputFormat'			=> array (),# Presentation format (CURRENTLY UNDOCUMENTED)
				'uploadDirectory'		=> NULL,	# Path to the file; any format acceptable
				'subfields'				=> 1,		# The number of widgets within the widget set (i.e. available file slots)
				'minimumRequired'		=> 0,		# The minimum number which must be selected (defaults to 0)
				'size'					=> 30,		# Visible size (optional; defaults to 30)
				'disallowedExtensions'	=> array (),# Simple array of disallowed file extensions (Single-item string also acceptable)
				'allowedExtensions'		=> array (),# Simple array of allowed file extensions (Single-item string also acceptable; '*' means extension required)
				'enableVersionControl'	=> true,	# Whether uploading a file of the same name should result in the earlier file being renamed
				'forcedFileName'		=> false,	# Force to a specific filename
		));
		
		# Ensure that arguments with a non-null default value are set (throwing an error if not), or assign the default value if none is specified
		foreach ($argumentsSpecification as $fieldType => $availableArguments) {
			foreach ($availableArguments as $argument => $defaultValue) {
				if (is_null ($defaultValue)) {
					if (!isSet ($arguments[$argument])) {
						$this->formSetupErrors['absent' . ucfirst ($fieldType) . ucfirst ($argument)] = "No '$argument' has been set for a specified $fieldType field.";
						${$argument} = $fieldType;
					} else {
						${$argument} = $arguments[$argument];
					}
				} else {
					${$argument} = (isSet ($arguments[$argument]) ? $arguments[$argument] : $defaultValue);
				}
			}
		}
		
		# Check whether the element name already been used accidentally (i.e. two fields with the same name) and if so add it to the array of duplicated element names
		if (isSet ($this->elements[$elementName])) {$this->duplicatedElementNames[] = $elementName;}
		
		# Flag that an upload element is present
		$this->uploadElementPresent = true;
		
		# Ensure the initial value(s) is an array, even if only an empty one, converting if necessary
		$disallowedExtensions = application::ensureArray ($disallowedExtensions);
		$allowedExtensions = application::ensureArray ($allowedExtensions);
		
		# Determine whether a file extension must be included - this is if * is the only value for $allowedExtensions; if so, also clear the array
		$extensionRequired = false;
		if (count ($allowedExtensions) == 1) {
			if ($allowedExtensions[0] == '*') {
				$extensionRequired = true;
				$allowedExtensions = array ();
			}
		}
		
		# Do not allow defining of both disallowed and allowed extensions at once, except for the special case of defining disallowed extensions plus requiring an extension
		if ((!empty ($disallowedExtensions)) && (!empty ($allowedExtensions)) && (!$extensionRequired)) {
			$this->formSetupErrors['uploadExtensionsMismatch'] = "You cannot, in the <strong>$elementName</strong> upload element, define <em>both</em> disallowed <em>and</em> allowed extensions.";
		}
		
		# Check that the number of available subfields is a whole number and that it is at least 1 (the latter error message overrides the first if both apply, e.g. 0.5)
		if ($subfields != round ($subfields)) {$this->formSetupErrors['uploadSubfieldsIncorrect'] = "You specified a non-whole number (<strong>$subfields</strong>) for the number of file upload widgets in the <strong>$elementName</strong> upload element which the form should create.";}
		if ($subfields < 1) {$this->formSetupErrors['uploadSubfieldsIncorrect'] = "The number of files to be uploaded must be at least one; you specified <strong>$subfields</strong> for the <strong>$elementName</strong> upload element.";}
		
		# Check that the minimum required is a whole number and that it is not greater than the number actually available
		if ($minimumRequired != round ($minimumRequired)) {$this->formSetupErrors['uploadSubfieldsMinimumIncorrect'] = "You specified a non-whole number (<strong>$minimumRequired</strong>) for the number of file upload widgets in the <strong>$elementName</strong> upload element which must the user must upload.";}
		if ($minimumRequired > $subfields) {$this->formSetupErrors['uploadSubfieldsMinimumMismatch'] = "The required minimum number of files which the user must upload (<strong>$minimumRequired</strong>) specified in the <strong>$elementName</strong> upload element is above the number of files actually available to be specified for upload (<strong>$subfields</strong>).";}
		
		# Check that the selected directory exists and is writable
		if (!is_dir ($uploadDirectory)) {$this->formSetupErrors['directoryNonexistent'] = "The directory specified for the <strong>$elementName</strong> upload element does not exist.";
		} else {
			if (!is_writable ($uploadDirectory)) {$this->formSetupErrors['directoryNotWritable'] = "The directory specified for the <strong>$elementName</strong> upload element is not writable. Please check that the file permissions to ensure that the webserver 'user' can write to the directory.";}
		}
		
		# Start a counter for the number of files apparently uploaded
		$apparentlyUploadedFiles = 0;
		
		# Start the HTML
		$widgetHtml = '';
		if ($subfields > 1) {$widgetHtml .= "\n\t\t\t";}
		
		# Loop through the number of fields required to create the widget and perform checks
		for ($subfield = 0; $subfield < $subfields; $subfield++) {
			
			# Continue further processing if the file has been uploaded
			if (isSet ($this->form[$elementName][$subfield])) {
				
				# Increment the number of apparently uploaded files (irrespective of whether they pass other checks)
				$apparentlyUploadedFiles++;
				
				# If an extension is required but the submitted item doesn't contain a dot, throw a problem
				if (($extensionRequired) && (strpos ($this->form[$elementName][$subfield]['name'], '.') === false)) {
					$extensionsMissing[] = $this->form[$elementName][$subfield]['name'];
				} else {
					
					# If the file is not valid, add it to a list of invalid subfields
					if (!application::filenameIsValid ($this->form[$elementName][$subfield]['name'], $disallowedExtensions, $allowedExtensions)) {
						$filenameInvalidSubfields[] = $this->form[$elementName][$subfield]['name'];
					}
				}
			}
			
			# Define the widget's core HTML
			$widgetHtml .= '<input name="' . $this->formName . "[$elementName][$subfield]\" type=\"file\" size=\"$size\" />";
			$widgetHtml .= (($subfield != ($subfields - 1)) ? "<br />\n\t\t\t" : (($subfields == 1) ? '' : "\n\t\t"));
		}
		
		# If fields which don't have a file extension have been found, throw a user error
		if (isSet ($extensionsMissing)) {
			$elementProblems['fileExtensionAbsent'] = (count ($extensionsMissing) > 1 ? 'The files ' : 'The file ') . implode (', ', $extensionsMissing) . (count ($extensionsMissing) > 1 ? ' have' : ' has') . ' no file extension, but file extensions are required for files selected in this section.';
		}
		
		# If fields which have an invalid extension have been found, throw a user error
		if (isSet ($filenameInvalidSubfields)) {
			$elementProblems['fileExtensionMismatch'] = (count ($filenameInvalidSubfields) > 1 ? 'All files ' : 'The file ') . implode (', ', $filenameInvalidSubfields) . (count ($filenameInvalidSubfields) > 1 ? ' do not' : ' does not') . ' comply with the specified file extension rules for this section.';
		}
		
		# If any files have been uploaded, assume an initial position that the user will be forced to reselect the files
		#!# Need a way of having this flagged in red
		if ($apparentlyUploadedFiles > 0) {$this->elementProblems['generic']['reselectUploads'] = 'You will need to reselect any selected files for uploading, for security reasons, because of problems elsewhere in the form.';}
		
		# Check if the field is required (i.e. the minimum number of fields is greater than 0) and, if so, run further checks
		if ($required = ($minimumRequired > 0)) {
			
			# If none have been uploaded, class this as requiredButEmpty
			if ($apparentlyUploadedFiles == 0) {
				$requiredButEmpty = true;
				
			# If too few have been uploaded, produce a individualised warning message
			} else if ($apparentlyUploadedFiles < $minimumRequired) {
				$elementProblems['underMinimum'] = ($minimumRequired != $subfields ? 'At least' : 'All') . " <strong>$minimumRequired</strong> " . ($minimumRequired > 1 ? 'files' : 'file') . ' must be submitted; you will need to reselect the ' . ($apparentlyUploadedFiles == 1 ? 'file' : "$apparentlyUploadedFiles files") . ' that you did previously select, for security reasons.';
			}
		}
		
		# Describe a restriction on the widget for minimum number of uploads
		if ($minimumRequired > 1) {$restrictions[] = "Minimum $minimumRequired items required";}
		
		# Describe extension restrictions on the widget and compile them as a semicolon-separated list
		if ($extensionRequired) {
			$restrictions[] = 'File extensions are required';
		} else {
			if (!empty ($allowedExtensions)) {
				$restrictions[] = 'Allowed file extensions: ' . implode (',', $allowedExtensions);
			}
		}
		if (!empty ($disallowedExtensions)) {
			$restrictions[] = 'Disallowed file extensions: ' . implode (',', $disallowedExtensions);
		}
		if (isSet ($restrictions)) {$restrictions = implode (";\n", $restrictions);}
		
		# Add the widget to the master array for eventual processing
		$this->elements[$elementName] = array (
			'type' => 'upload',
			'html' => $widgetHtml,
			'title' => $title,
			'description' => $elementDescription,
			'restriction' => (isSet ($restrictions) ? $restrictions : false),
			'problems' => (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $required,
			'requiredButEmpty' => (isSet ($requiredButEmpty) ? $requiredButEmpty : false),
			'uploadDirectory' => $uploadDirectory,
			'subfields' => $subfields,
			'suitableAsEmailTarget' => false,
			'outputFormat' => $outputFormat,
			'enableVersionControl' => $enableVersionControl,
			'forcedFileName' => $forcedFileName,
		);
	}
	
	
	/**
	 * Function to pass hidden data over
	 * @param array $arguments Supplied arguments - see template
	 */
	function hidden ($arguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentsSpecification = array (
			'hidden' => array (
				'valuesArray'			=> array (),		# Associative array of selectable values
				'elementName'			=> '',				# Name of the element (Optional)
				'outputFormat'			=> array (),		# Presentation format (CURRENTLY UNDOCUMENTED)
				'title'					=> 'Hidden data',	# Title (CURRENTLY UNDOCUMENTED)
		));
		
		# Ensure that arguments with a non-null default value are set (throwing an error if not), or assign the default value if none is specified
		foreach ($argumentsSpecification as $fieldType => $availableArguments) {
			foreach ($availableArguments as $argument => $defaultValue) {
				if (is_null ($defaultValue)) {
					if (!isSet ($arguments[$argument])) {
						$this->formSetupErrors['absent' . ucfirst ($fieldType) . ucfirst ($argument)] = "No '$argument' has been set for a specified $fieldType field.";
						${$argument} = $fieldType;
					} else {
						${$argument} = $arguments[$argument];
					}
				} else {
					${$argument} = (isSet ($arguments[$argument]) ? $arguments[$argument] : $defaultValue);
				}
			}
		}
		
		# Check whether the element name already been used accidentally (i.e. two fields with the same name) and if so add it to the array of duplicated element names
		if (isSet ($this->elements[$elementName])) {$this->duplicatedElementNames[] = $elementName;}
		
		# Flag that a hidden element is present
		$this->hiddenElementPresent = true;
		
		# Ensure the elementName is not empty
		if ($elementName == '') {$elementName = 'hidden';}
		
		# Check that the values array is actually an array
		if (!is_array ($valuesArray)) {$this->formSetupErrors['hiddenElementNotArray'] = "The hidden data specified for the <strong>$elementName</strong> hidden input element must be an array but is not currently.";}
		
		#!# Need to add a check for a non-empty valuesArray
		
		# Loop through each hidden data sub-array and create the HTML
		$widgetHtml = "\n";
		foreach ($valuesArray as $key => $value) {
			$widgetHtml .= "\n" . '<input type="hidden" name="' . $this->formName . "[$elementName][$key]" . '" value="' . $value . '" />';
		}
		$widgetHtml .= "\n";
		
		# Add the widget to the master array for eventual processing
		$this->elements[$elementName] = array (
			'type' => 'hidden',
			'html' => $widgetHtml,
			'title' => $title,
			'restriction' => false,
			'description' => false,
			'problems' => false,
			'required' => true,
			'requiredButEmpty' => false,
			'suitableAsEmailTarget' => false,
			'outputFormat' => $outputFormat,
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
				$widgetHtml = "<p>$title</p>";
				break;
			case 'text':
			case '':
				$widgetHtml = $title;
				break;
			default:
				$widgetHtml = "<h$level>$title</h$level>";
				break;
		}
		
		# Add the widget to the master array for eventual processing
		$this->elements['_heading' . $this->headingTextCounter++] = array (
			'type' => 'heading',
			'html' => $widgetHtml,
			'title' => '',
			'description' => false,
			'restriction' => false,
			'problems' => false,
			'required' => false,
			'requiredButEmpty' => false,
			'outputFormat' => array (),	// The outputFormat specification must always be array
		);
	}
	
	
	# Function to determine whether an array of values for a select form is suitable as an e-mail target
	function suitableAsEmailTarget ($valuesArray)
	{
		# Ensure the values are an array
		$valuesArray = application::ensureArray ($valuesArray);
		
		# Return true if all e-mails are valid
		$allValidEmail = true;
		foreach ($valuesArray as $value) {
			if (!application::validEmail ($value)) {
				$allValidEmail = false;
				break;
			}
		}
		if ($allValidEmail) {return true;}
		
		# If any of the suffixed ones would not be valid as an e-mail, then flag 'syntax'
		foreach ($valuesArray as $value) {
			if (!application::validEmail ($value . '@example.com')) {
				return 'syntax';
			}
		}
		
		# Otherwise return that a suffix would be required
		return 'suffix';
	}
	
	
	/**
	 * Function to merge the multi-part posting of files to the main form array, effectively emulating the structure of a standard form input type
	 * @access private
	 */
	function mergeFilesIntoPost ()
	{
		# Loop through each upload widget set which has been submitted (even if empty)
		foreach ($_FILES[$this->formName]['name'] as $elementName => $subElements) {
			
			# Loop through each upload widget set's subelements (e.g. 4 items if there are 4 input tags within the widget set)
			foreach ($subElements as $key => $value) {
				
				# Map the file information into the main form element array
				if (!empty ($value)) {
					$this->form[$elementName][$key] = array (
						'name' => $_FILES[$this->formName]['name'][$elementName][$key],
						'type' => $_FILES[$this->formName]['type'][$elementName][$key],
						'tmp_name' => $_FILES[$this->formName]['tmp_name'][$elementName][$key],
						#'error' => $_FILES[$this->formName]['error'][$elementName][$key],
						'size' => $_FILES[$this->formName]['size'][$elementName][$key],
					);
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
		echo "\n\n" . '<div class="debug">';
		echo "\n\n<h2>Debugging information</h2>";
		echo "\n\n<ul>";
		echo "\n\n\t" . '<li><a href="#configured">Configured form elements - $this->elements</a></li>';
		if ($this->formPosted) {echo "\n\n\t" . '<li><a href="#submitted">Submitted form elements - $this->form</a></li>';}
		echo "\n\n\t" . '<li><a href="#remainder">Any form setup errors; then: Remainder of form</a></li>';
		echo "\n\n</ul>";
		
		# Show configured form elements
		echo "\n\n" . '<h3 id="configured">Configured form elements - $this->elements :</h3>';
		application::dumpData ($this->elements);
		
		# Show submitted form elements, if the form has been submitted
		if ($this->formPosted) {
			echo "\n\n" . '<h3 id="submitted">Submitted form elements - $this->form :</h3>';
			application::dumpData ($this->form);
		}
		
		# End the debugging HTML
		echo "\n\n" . '<a name="remainder"></a>';
		echo "\n</div>";
	}
	
	
	/**
	 * Function to clean whitespace from a field where requested
	 * @access private
	 */
	function handleWhiteSpace ($element)
	{
		# Trim white space if required
		if ($this->whiteSpaceTrimSurrounding) {$element = trim ($element);}
		
		# Remove white space if that's all there is
		if (($this->whiteSpaceCheatAllowed) && (trim ($element)) == '') {$element = '';}
		
		# Return the cleaned field
		return $element;
	}
	
	
	/**
	 * Function to clean input from a field to being numeric only
	 * @access private
	 */
	function cleanToNumeric ($data)
	{
		#!# Replace with something like this line? :
		#$this->form[$elementName] = ereg_replace ('[^0-9\. ]', '', trim ($this->form[$elementName]));
		
		# Strip replace windows carriage returns with a new line (multiple new lines will be stripped later)
		$data = ereg_replace ("\r", "\n", $data);
		# Turn commas into spaces
		$data = ereg_replace (",", " ", $data);
		# Strip non-numeric characters
		$data = ereg_replace ("[^-0-9\.\n\t ]", "", $data);
		# Replace tabs and duplicated spaces with a single space
		$data = ereg_replace ("\t", " ", $data);
		# Replace tabs and duplicated spaces with a single space
		$data = ereg_replace ("[ \t]+", " ", $data);
		# Remove space at the start and the end
		$data = trim ($data);
		# Collapse duplicated newlines
		$data = ereg_replace ("[\n]+", "\n", $data);
		# Remove any space at the start or end of each line
		$data = ereg_replace ("\n ", "\n", $data);
		$data = ereg_replace (" \n", "\n", $data);
		
		# Return the result
		return $data;
	}
	
	
	## Deal with form output ##
	
	/**
	 * Output the result as an e-mail
	 */
	#!# Not fully tested yet
	function setOutputEmail ($recipient, $administrator = '', $subjectTitle = 'Form submission results', $chosenElementSuffix = NULL, $replyToField = NULL)
	{
		# Flag that this method is required
		$this->outputMethods['email'] = true;
		
		# If the recipient is an array, split it into a recipient as the first and cc: as the remainder:
		if (is_array ($recipient)) {
			$recipientList = $recipient;
			$recipient = array_shift ($recipientList);
			$this->configureResultEmailCc = $recipientList;
		}
		
		# Assign the recipient by default to $recipient
		$this->configureResultEmailRecipient = $recipient;
		
		# If the recipient is not a valid e-mail address then assume that it should be taken from a field
		if (!application::validEmail ($recipient)) {
			
			# If the recipient is supposed to be a form field, start by checking that an existent field is supplied
			if (!isSet ($this->elements[$recipient])) {
				$this->formSetupErrors['setOutputEmailElementNonexistent'] = "The chosen field (<strong>$recipient</strong>) (which has been specified as an alternative to a valid e-mail address) for the submitter's confirmation e-mail does not exist.";
			} else {
				
				# If the field type is not suitable as an e-mail target, throw a setup error
				if (!$this->elements[$recipient]['suitableAsEmailTarget']) {
					$this->formSetupErrors['setOutputEmailElementInvalid'] = "The chosen field (<strong>$recipient</strong>) is not a valid field from which the recipient of the result-containing e-mail can be taken.";
				} else {
					
					# If the field type is a suitable type but the possible results are not all syntactically valid, then say so
					#R# This sort of check should be done before now; refactor to avoid passing keywords such as 'syntax' and 'suffix'
					if ($this->elements[$recipient]['suitableAsEmailTarget'] === 'syntax') {
						$this->formSetupErrors['setOutputEmailElementWidgetSuffixInvalid'] = "The results for the chosen field (<strong>$recipient</strong>) for the receipient of the result-containing e-mail are not all usable as the prefix for a valid e-mail address, even though this has been specified as the field from which the e-mail recipient is taken.";
					} else {
						
						# If a suffix has been supplied, ensure that it will make a valid e-mail address
						#R# Again, this sort of check should be done before now
						if ($this->elements[$recipient]['suitableAsEmailTarget'] === 'suffix') {
							if (empty ($chosenElementSuffix)) {
								$this->formSetupErrors['setOutputEmailElementSuffixMissing'] = "The chosen field (<strong>$recipient</strong>) for the receipient of the result-containing e-mail must have a suffix supplied within the e-mail output specification.";
							} else {
								#!# The use of 'foo' is a hack to supply ::validEmail with a full e-mail rather than just the domain part - need to replace ::validEmail with a ::validEmailDomain regexp instead
								if (!application::validEmail ('foo' . (substr ($chosenElementSuffix, 0, 1) != '@' ? '@' . $chosenElementSuffix : $chosenElementSuffix))) {
									$this->formSetupErrors['setOutputEmailElementSuffixInvalid'] = "The e-mail suffix specified for the chosen field (<strong>$recipient</strong>) for the receipient of the result-containing e-mail contains a syntax error.";
								} else {
									
									# As the suffix is confirmed requried and valid, assign the recipient suffix
									$this->configureResultEmailRecipientSuffix = $chosenElementSuffix;
								}
							}
						}
					}
				}
			}
		}
		
		# Assign the administrator by default to $administrator; if none is specified, use the SERVER_ADMIN, otherwise use the supplied administrator if that is a valid e-mail address
		#R# Refactor this section to a new method returning the required value
		#!# This next line should not be required - it is being duplicated, or a setup error should be thrown
		$this->configureResultEmailAdministrator = $administrator;
		if (!$administrator) {
			$this->configureResultEmailAdministrator = $_SERVER['SERVER_ADMIN'];
		} else {
			if (application::validEmail ($administrator)) {
				$this->configureResultEmailAdministrator = $administrator;
			} else {
				
				# If the address includes an @ but is not a valid address, state this as an error
				#!# What is the point of this?
				if (strpos ($administrator, '@') !== false) {
					$this->formSetupErrors['setOutputEmailReceipientEmailSyntaxInvalid'] = "The chosen e-mail sender address (<strong>$administrator</strong>) contains an @ symbol but is not a valid e-mail address.";
				} else {
					
					# If not a valid e-mail address check for an existent and then valid field name
					if (!isSet ($this->elements[$administrator])) {
						$this->formSetupErrors['setOutputEmailReceipientInvalid'] = "The chosen e-mail sender address (<strong>$administrator</strong>) is a non-existent field name.";
					} else {
						if ($this->elements[$administrator]['type'] != 'email') {
							$this->formSetupErrors['setOutputEmailReceipientInvalidType'] = "The chosen e-mail sender address (<strong>$administrator</strong>) is not an e-mail type field name.";
						}
					}
				}
			}
		}
		
		# Set the reply-to field if applicable
		$this->configureResultEmailReplyTo = $replyToField;
		if ($replyToField) {
			if (!isSet ($this->elements[$replyToField])) {
				$this->formSetupErrors['setOutputEmailReplyToFieldInvalid'] = "The chosen e-mail reply-to address (<strong>$replyToField</strong>) is a non-existent field name.";
				$this->configureResultEmailReplyTo = NULL;
			} else {
				if (($this->elements[$replyToField]['type'] != 'email') && ($this->elements[$replyToField]['type'] != 'input')) {
					$this->formSetupErrors['setOutputEmailReplyToFieldInvalidType'] = "The chosen e-mail reply-to address (<strong>$replyToField</strong>) is not an e-mail/input type field name.";
					$this->configureResultEmailReplyTo = NULL;
				}
			}
		}
		
		# Assign the subject title
		$this->configureResultEmailedSubjectTitle['email'] = $subjectTitle;
	}
	
	
	/**
	 * Output a confirmation of the submitted results to the submitter
	 */
	function setOutputConfirmationEmail ($chosenElementName, $administrator = '', $includeAbuseNotice = true, $subjectTitle = 'Form submission results')
	{
		# Flag that this method is required
		$this->outputMethods['confirmationEmail'] = true;
		
		# Throw a setup error if the element name for the chosen e-mail field doesn't exist or it is not an e-mail type
		#!# Allow text-field types to be used if a hostname part is specified, or similar
		if (!isSet ($this->elements[$chosenElementName])) {
			$this->formSetupErrors['setOutputConfirmationEmailElementNonexistent'] = "The chosen field (<strong>$chosenElementName</strong>) for the submitter's confirmation e-mail does not exist.";
		} else {
			if ($this->elements[$chosenElementName]['type'] != 'email') {
				$this->formSetupErrors['setOutputConfirmationEmailTypeMismatch'] = "The chosen field (<strong>$chosenElementName</strong>) for the submitter's confirmation e-mail is not an e-mail field type.";
			} else {
				
				# If the form has been posted and the relevant element is assigned, assign the recipient (i.e. the submitter's) e-mail address (which is validated by this point)
				if ($this->formPosted) {
					#!# As noted later on, this really must be replaced with a formSetupErrors call here
					if (!empty ($this->form[$chosenElementName])) {
						$this->configureResultConfirmationEmailRecipient = $this->form[$chosenElementName];
					}
				}
			}
		}
		
		# Assign whether to include an abuse report notice
		$this->configureResultConfirmationEmailAbuseNotice = $includeAbuseNotice;
		
		# Assign the administrator e-mail address
		$this->configureResultConfirmationEmailAdministrator = ($administrator != '' ? $administrator : $_SERVER['SERVER_ADMIN']);
		
		# Assign the subject title
		$this->configureResultEmailedSubjectTitle['confirmationEmail'] = $subjectTitle;
	}
	
	
	/**
	 * Output the results to a CSV file
	 */
	function setOutputFile ($filename)
	{
		# Flag that this method is required
		$this->outputMethods['file'] = true;
		
		#!# Need to add a timestamp-writing option
		
		# Attempt to create the file (as an empty file) if it doesn't exist
		#!# Replace with an is_writable () check, as writeDataToFile returns bytes (which will be 0) if success
		if (!file_exists ($filename)) {
			if (!application::writeDataToFile ('', $filename)) {
				$this->formSetupErrors['resultsFileNotCreatable'] = 'The specified results file cannot be created; please check the permissions for the containing directory.';
			}
		} else {
			
			# If it does exist, check instead whether it is writable
			#!# Problem here if not exists first time, etc...
			if (!is_writable ($filename)) {$this->formSetupErrors['resultsFileNotWritable'] = 'The specified results file is not writable; please check its permissions.';}
		}
		
		# Assign the file location
		$this->configureResultFileFilename = $filename;
	}
	
	
	/**
	 * Output (display) the results on screen
	 */
	function setOutputScreen ()
	{
		# Flag that this method is required
		$this->outputMethods['screen'] = true;
	}
	
	
	# Function to return the specification
	function getSpecification ()
	{
		# Return the elements array
		return $this->elements;
	}
	
	
	# Function to add built-in hidden security fields
	#!# This and hiddenSecurityFieldSubmissionInvalid () should be refactored into a small class
	function addHiddenSecurityFields ()
	{
		# Firstly (since username may be in use as a key) create a hidden username if required and a username is supplied
		$userCheckInUse = ($this->user && $this->userKey);
		if ($userCheckInUse) {
			$securityFields['user'] = $this->user;
		}
		
		# Create a hidden timestamp if necessary
		if ($this->timestamping) {
			$securityFields['timestamp'] = $this->timestamp;
		}
		
		# Make an internal call to the external interface
		#!# Add security-verifications as a reserved word
		if (isSet ($securityFields)) {
			$this->hidden (array (
			    'elementName'	=> 'security-verifications',
				'valuesArray'	=> $securityFields,
			));
		}
	}
	
	
	# Function to validate built-in hidden security fields
	function hiddenSecurityFieldSubmissionInvalid ()
	{
		# End checking if the form is not posted or there is no username
		if (!$this->formPosted || !$this->user || !$this->userKey) {return false;}
		
		# Check for faked submissions
		if ($this->form['security-verifications']['user'] != $this->user) {
			$this->elementProblems = "\n" . '<p class="warning">The username which was silently submitted (' . $this->form['security-verifications']['user'] . ') does not match the username you previously logged in as (' . $this->user . '). This has been reported as potential abuse and will be investigated.</p>';
			error_log ("A potentially fake submission has been made by {$this->user}, claiming to be {$this->form['security-verifications']['user']}. Please investigate.");
			#!# Should this really force ending of further checks?
			return true;
		}
		
		# If user uniqueness check is required, check that the user has not already made a submission
		if ($this->loggedUserUnique) {
			$csvData = application::getCsvData ($this->configureResultFileFilename);
			/* #!# Can't enable this section until application::getCsvData recognises the difference between an empty file and an unopenable/missing file
			if (!$csvData) {
				$this->formSetupErrors['csvInaccessible'] = 'It was not possible to make a check for repeat submissions as the data source could not be opened.';
				return true;
			} */
			if (array_key_exists ($this->user, $csvData)) {
				echo "\n" . '<p class="warning">You appear to have already made a submission. If you believe this is not the case, please contact the webmaster to resolve the situation.</p>';
				return true;
			}
		}
		
		# Otherwise return false (i.e. that there were no problems)
		return false;
	}
	
	
	
	## Main processing ##
	
	/**
	 * Process/display the form (main wrapper function)
	 */
	function processForm ()
	{
		# Show the presentation matrix if required (this is allowed to bypass the form setup so that the administrator can see what corrections are needed)
		if ($this->showPresentationMatrix) {$this->showPresentationMatrix ();}
		
		# Check if the form and PHP environment has been set up OK
		if (!$this->formSetupOk ()) {return false;}
		
		# Show debugging information firstly if required
		if ($this->debug) {$this->showDebuggingInformation ();}
		
		# Check whether the user is a valid user (must be before the formSetupOk check)
		if (!$this->validUser ()) {return false;}
		
		# Check whether the facility is open
		if (!$this->facilityIsOpen ($this->opening, $this->closing)) {return false;}
		
		# Validate hidden security fields
		if ($this->hiddenSecurityFieldSubmissionInvalid ()) {return false;}
		
		# Run checks if the form has been posted
		$problemsFound = ($this->formPosted ? $this->checkForElementProblems () : false);
		
		# If the form is not posted or contains problems, display it and flag that it has been displayed
		if (!$this->formPosted || $problemsFound) {
			echo $this->constructFormHtml ($this->elements, $this->elementProblems);
			return false;
		}
		
		# Prepare the data
		$this->outputData = $this->prepareData ();
		
		# If required, display a summary confirmation of the result
		if ($this->showFormCompleteText) {echo "\n" . '<p class="completion">' . $this->formCompleteText . ' </p>';}
		
		# Loop through each of the processing methods and output it based on the requested method
		foreach ($this->outputMethods as $outputType => $required) {
			$this->outputData ($outputType);
		}
		
		# If required, display a link to reset the page
		if ($this->showFormCompleteText) {echo "\n" . '<p><a href="' . $_SERVER['REQUEST_URI'] . '">Click here to reset the page.</a></p>';}
		
		# Return the data
		return $this->outputData ('processing');
	}
	
	
	## Form processing support ##
	
	# Function to determine whether this facility is open
	function facilityIsOpen ($opening, $closing)
	{
		# Check that the opening time has passed, if one is specified, ensuring that the date is correctly specified
		if (!is_null ($opening)) {
			if (time () < $opening) {
				echo '<p class="warning">This facility is not yet open. Please return later.</p>';
				return false;
			}
		}
		
		# Check that the closing time has passed
		if (!is_null ($closing)) {
			if (time () > $closing) {
				echo '<p class="warning">This facility is now closed.</p>';
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
		if (!$this->validUsers) {return true;}
		
		# If '*' is specified for valid users, allow any through
		if ($this->validUsers[0] == '*') {return true;}
		
		# If the username is supplied in a list, return true
		if (in_array ($this->user, $this->validUsers)) {return true;}
		
		# Otherwise state that the user is not in the list and return false
		echo "\n" . '<p class="warning">You do not appear to be in the list of valid users. If you believe you should be, please contact the webmaster to resolve the situation.</p>';
		return false;
	}
	
	
	/**
	 * Function to check for form setup errors
	 * @todo Add all sorts of other form setup checks as flags within this function
	 * @access private
	 */
	function formSetupOk ()
	{
		# Check the PHP environment set up is OK
		$this->validEnvironment ();
		
		# Check that there are no namespace clashes against internal defaults
		$this->preventNamespaceClashes ();
		
		# If a user is to be required, ensure there is a server-supplied username
		if ($this->validUsers && !$this->user) {$this->formSetupErrors['usernameMissing'] = 'No username is being supplied, but the form setup requires that one is supplied, either explicitly or implicitly through the server environment. Please check the server configuration.';}
		
		# If a user uniqueness check is required, ensure that the file output mode is in use and that the user is being logged as a CSV key
		if ($this->loggedUserUnique && !$this->outputMethods['file']) {$this->formSetupErrors['loggedUserUniqueRequiresFileOutput'] = "The settings specify that usernames are checked for uniqueness against existing submissions, but no log file of submissions is being made. Please ensure that the 'file' output type is enabled if wanting to check for uniqueness.";}
		if ($this->loggedUserUnique && !$this->userKey) {$this->formSetupErrors['loggedUserUniqueRequiresUserKey'] = 'The settings specify that usernames are checked for uniqueness against existing submissions, but usernames are not set to be logged in the data. Please ensure that both are enabled if wanting to check for uniqueness.';}
		
		# Check the opening and closing time syntax is correct if they are supplied
		if ($this->opening === -1 || $this->closing === -1) {$this->formSetupErrors['availabilityWronglySpecified'] = 'The ' . ($this->opening === -1 ? 'opening' : '') . ($this->opening === -1 && $this->closing === -1 ? ' and ' : '') . ($this->closing === -1 ? 'closing' : '') . ($this->opening === -1 && $this->closing === -1 ? ' times are' : ' time is') . ' not correctly specified. Please correct the syntax.';}
		
		# Check that an empty form hasn't been requested (i.e. there must be at least one form field)
		#!# This needs to be modified to take account of headers (which should not be included)
		if (empty ($this->elements)) {$this->formSetupErrors['formEmpty'] = 'No form elements have been defined (i.e. the form is empty).';}
		
		# If there are any duplicated keys, list each duplicated key in bold with a comma between (but not after) each
		if (!empty ($this->duplicatedElementNames)) {$this->formSetupErrors['duplicatedElementNames'] = 'The following field ' . (count (array_unique ($this->duplicatedElementNames)) == 1 ? 'name has' : 'names have been') . ' been duplicated in the form setup: <strong>' . implode ('</strong>, <strong>', array_unique ($this->duplicatedElementNames)) .  '</strong>.';}
		
		# Validate the output format syntax items, looping through each and adding it to an array of items if an mispelt/unsupported item is found
		#!# Move this into a new widget object's constructor
		$formatSyntaxInvalidElements = array ();
		foreach ($this->elements as $name => $elementAttributes) {
			if (!$this->outputFormatSyntaxValid ($elementAttributes['outputFormat'])) {
				$formatSyntaxInvalidElements[$name] = true;
			}
		}
		if (!empty ($formatSyntaxInvalidElements)) {$this->formSetupErrors['outputFormatMismatch'] = 'The following field ' . (count ($formatSyntaxInvalidElements) == 1 ? 'name has' : 'names have') . ' an incorrectly set up output format specification in the form setup: <strong>' . implode ('</strong>, <strong>', array_keys ($formatSyntaxInvalidElements)) .  '</strong>; the administrator should switch on the \'showPresentationMatrix\' option in the settings to check the syntax.';}
		
		# Check that the output format for each item against each output type is valid
		#!# This could probably do with refactoring to a separate function once the functionality is moved into the new widget object's constructor
		$formatUnsupportedElements = array ();
		$availableOutputFormats = $this->presentationDefaults ($returnFullAvailabilityArray = true, $includeDescriptions = false);
		foreach ($this->elements as $name => $elementAttributes) {  // Loop through each administrators's setup
			$widgetType = $this->elements[$name]['type'];
			foreach ($elementAttributes['outputFormat'] as $outputFormat => $setting) {  // Loop through each of the output formats specified in the administrators's setup
				if (!in_array ($setting, $availableOutputFormats[$widgetType][$outputFormat])) {
					$formatUnsupportedElements[$name] = true;
				}
			}
		}
		if (!empty ($formatUnsupportedElements)) {$this->formSetupErrors['outputFormatUnsupported'] = 'The following field ' . (count ($formatSyntaxInvalidElements) == 1 ? 'name has' : 'names have') . ' been allocated an output specification which is unsupported for the required output format(s) in the form setup: <strong>' . implode ('</strong>, <strong>', array_keys ($formatUnsupportedElements)) .  '</strong>; switching on the \'showPresentationMatrix\' option in the settings will display the available types.';}
		
		# If there are any form setup errors - a combination of those just defined and those assigned earlier in the form processing, show them
		if (!empty ($this->formSetupErrors)) {echo application::showUserErrors ($this->formSetupErrors, $parentTabLevel = 1, (count ($this->formSetupErrors) > 1 ? 'Various errors were' : 'An error was') . " found in the setup of the form. The website's administrator needs to correct the configuration before the form will work:");}
		
		# Set that the form has effectively been displayed
		$this->formDisplayed = true;
		
		# Return true (i.e. form set up OK) if the errors array is empty
		return (empty ($this->formSetupErrors));
	}
	
	
	/**
	 * Function to validate the output format syntax
	 * @access private
	 */
	function outputFormatSyntaxValid ($elementOutputSpecificationArray)
	{
		# Define the supported types and values
		#!# This should ideally be picked up somewhere else
		$supportedTypes = array ('file', 'email', 'confirmationEmail', 'screen', 'processing');
		$supportedValues = array ('presented', 'compiled', 'rawcomponents');
		
		# If the element output specification array includes some items, check that the types and values are within the list of supported types and values
		if (!empty ($elementOutputSpecificationArray)) {
			foreach ($elementOutputSpecificationArray as $type => $value) {
				if ((!in_array ($type, $supportedTypes)) || (!in_array ($value, $supportedValues))) {
					return false;
				}
			}
		}
		
		# Otherwise return true
		return true;
	}
	
	
	/**
	 * Function to perform validity checks to ensure a correct PHP environment
	 * @access private
	 */
	function validEnvironment ()
	{
		# Check the minimum PHP version, to ensure that all required functions will be available
		$minimumPhpVersion = 4.2; // md5_file requires 4.2 or above
		#!# Write a fake replacement function for md5_file if the function doesn't exist (e.g. just returns 1). taking care of scope
		if (PHP_VERSION < $minimumPhpVersion) {$this->formSetupErrors['environmentPhpVersion'] = 'The server must be running PHP version <strong>' . $minimumPhpVersion . '</strong> or higher.';}
		
		# Check that global user variables cannot be imported into the program
		#!# Investigate adding (bool) as noted in notes to php.net/ini-get
		if (ini_get ('register_globals')) {$this->formSetupErrors['environmentRegisterGlobals'] = 'The PHP configuration setting register_globals must be set to <strong>off</strong>.';}
		
		# Check that raw PHP errors are not set to display on the screen
		if (!$this->developmentEnvironment) {
			if (ini_get ('display_errors')) {$this->formSetupErrors['environmentDisplayErrors'] = 'The PHP configuration setting display_errors must be set to <strong>false</strong>.';}
		}
		
		# Check that magic_quotes are switched off; escaping of user input is handled manually
		if (ini_get ('magic_quotes_gpc')) {$this->formSetupErrors['environmentMagicQuotesGpc'] = 'The PHP configuration setting magic_quotes_gpc must be set to <strong>off</strong>.';}
		
		# Perform checks on upload-related settings if any elements are upload types and the check has not been run
		if ($this->uploadElementPresent) {
			
			# Ensure file uploads are allowed
			if (!ini_get ('file_uploads')) {
				$this->formSetupErrors['environmentFileUploads'] = 'The PHP configuration setting file_uploads must be set to <strong>on</strong> given that the form includes an upload element.';
			} else {
				
				# If file uploads are being allowed, check that upload_max_filesize and post_max_size are valid
				if ((!preg_match ('/^(\d+)([bkm]*)$/i', ini_get ('upload_max_filesize'))) || (!preg_match ('/^(\d+)([bkm]*)$/i', ini_get ('post_max_size')))) {
					$this->formSetupErrors['environmentFileUploads'] = 'The PHP configuration setting upload_max_filesize/post_max_size must both be valid.';
				} else {
					
					# Given that file uploads are being allowed and the ensure that the upload size is not greater than the maximum POST size
					if (application::convertSizeToBytes (ini_get ('upload_max_filesize')) > application::convertSizeToBytes (ini_get ('post_max_size'))) {$this->formSetupErrors['environmentFileUploads'] = 'The PHP configuration setting upload_max_filesize cannot be greater than post_max_filesize; the form includes an upload element, so this misconfiguration must be corrected.';}
				}
			}
		}
	}
	
	
	/**
	 * Function to check for namespace clashes against internal defaults
	 * @todo Ideally replace each clashable item with an encoding method somehow or ideally eradicate the restrictions
	 * @access private
	 */
	function preventNamespaceClashes ()
	{
		# Define a list of reserved names
		$reservedFormNames = array ('MAX_FILE_SIZE', );
		
		# Check through the list of reserved form names
		foreach ($reservedFormNames as $reservedFormName) {
			if ($this->formName == $reservedFormName) {
				$this->formSetupErrors['namespaceFormNameReserved'] = 'The name of the form cannot be ' . $reservedFormName;
				break;
			}
		}
		
		# Disallow [ or ] in a form name
		if ((strpos ($this->formName, '[') !== false) || (strpos ($this->formName, ']') !== false)) {
			$this->formSetupErrors['namespaceFormNameContainsSquareBrackets'] = 'The name of the form ('. $this->formName . ') cannot include square brackets.';
		}
		
		#!# Need a check to disallow valid e-mail addresses as an element name, or encode - this is to prevent setOutputEmail () picking a form element which should actually be an e-mail address
		
		# Disallow _heading at the start of an element
		#!# This will also be listed alongside the 'Element names cannot start with _heading'.. warning
		foreach ($this->elements as $elementName => $elementAttributes) {
			if (ereg ('^_heading', $elementName)) {
				if ($elementAttributes['type'] != 'heading') {
					$disallowedElementNames[] = $elementName;
				}
			}
		}
		if (isSet ($disallowedElementNames)) {
			$this->formSetupErrors['namespaceElementNameStartDisallowed'] = 'Element names cannot start with _heading; the <strong>' . implode ('</strong>, <strong>', $disallowedElementNames) . '</strong> elements must therefore be renamed.';
		}
		
		#!# Convert this to returning an array which gets merged with the formSetupErrors array
	}
	
	
	/**
	 * Function actually to display the form
	 * @access private
	 */
	function constructFormHtml ($elements, $problems)
	{
		# Open an enclosing <div> for stylesheet hooking
		$html  = "\n" . '<div class="ultimateform">';
		
		# Start with any list of problems
		$html .= "\n" . $this->problemsList ($problems);
		
		# Start the constructed form HTML
		$html .= "\n" . '<form method="post" action="' . $this->submitTo . '" enctype="' . ($this->uploadElementPresent ? 'multipart/form-data' : 'application/x-www-form-urlencoded') . '">';
		
		#!# If an upload widget is needed, insert a client-side max-file-size tag
		#if ($this->uploadElementPresent) {$html .= "\n\t" . '<input type="hidden" name="MAX_FILE_SIZE" value="' . application::convertSizeToBytes (ini_get ('upload_max_filesize')) . '" />';}
		
		# Start the HTML
		$formHtml = '';
		$hiddenHtml = '';
		
		# Loop through each of the elements to construct the form HTML
		foreach ($elements as $elementName => $elementAttributes) {
			
			# For hidden elements, buffer the hidden HTML
			if ($elementAttributes['type'] == 'hidden') {
				$hiddenHtml .= $elementAttributes['html'];
				
			# For all other elements, construct the HTML directly, dependent on the display method selected
			} else {
				
				# If colons are set to show, add them
				if ($this->displayColons) {$elementAttributes['title'] .= ':';}
				
				# If the element is required, add an indicator
				if ($elementAttributes['required']) {$elementAttributes['title'] .= '&nbsp;*';}
				
				# If the form has been posted AND the element has any problems or is empty, add the warning CSS class
				if (($this->formPosted) && (($elementAttributes['problems']) || ($elementAttributes['requiredButEmpty']))) {$elementAttributes['title'] = '<span class="warning">' . $elementAttributes['title'] . '</span>';}
				
				# Select whether to show restriction guidelines
				$displayRestriction = (($this->displayRestrictions) && ($elementAttributes['restriction'] != ''));
				
				# Display the display text (in the required format), unless it's a hidden array (i.e. no elementText to appear)
				switch ($this->display) {
					
					# Display as paragraphs
					case 'paragraphs':
						if ($elementAttributes['type'] == 'heading') {
							$formHtml .= "\n" . $elementAttributes['html'];
						} else {
							$formHtml .= "\n" . '<p id="' . $elementName . '">';
							$formHtml .= "\n\t";
							if ($this->displayTitles) {
								$formHtml .= $elementAttributes['title'] . '<br />';
								if ($displayRestriction) {$formHtml .= "<br /><span class=\"restriction\">(" . ereg_replace ("\n", '<br />', $elementAttributes['restriction']) . ')</span>';}
							}
							$formHtml .= $elementAttributes['html'];
							if ($this->displayDescriptions) {if ($elementAttributes['description'] != '') {$formHtml .= "<br />\n<span class=\"description\">" . $elementAttributes['description'] . '</span>';}}
							$formHtml .= "\n</p>";
						}
						break;
						
					# Display using divs for CSS layout mode; this is different to paragraphs as the form fields are not conceptually paragraphs
					#!# Should these really be spans? - test readability by removing the stylesheet!
					case 'css':
						$formHtml .= "\n" . '<div class="row" id="' . $elementName . '">';
						if ($elementAttributes['type'] == 'heading') {
							$formHtml .= "\n\t<span class=\"title\">" . $elementAttributes['html'] . '</span>';
						} else {
							$formHtml .= "\n\t";
							if ($this->displayTitles) {
								$formHtml .= "<span class=\"label\">" . $elementAttributes['title'] . '</span>';
								if ($displayRestriction) {$formHtml .= "\n\t<span class=\"restriction\">(" . ereg_replace ("\n", '<br />', $elementAttributes['restriction']) . ')</span>';}
							}
							$formHtml .= "\n\t<span class=\"data\">" . $elementAttributes['html'] . '</span>';
							if ($this->displayDescriptions) {if ($elementAttributes['description'] != '') {$formHtml .= "\n\t<span class=\"description\">" . $elementAttributes['description'] . '</span>';}}
						}
							$formHtml .= "\n</div>";
						break;
						
					# By default, display as tables
					default:
						# Start by determining the number of columns which will be needed for headings involving a colspan
						$colspan = 1 + ($this->displayTitles) + ($this->displayDescriptions);
						$formHtml .= "\n\t" . '<tr class="' . $elementName . '">';
						if ($elementAttributes['type'] == 'heading') {
							$formHtml .= "\n\t\t<td colspan=\"$colspan\">" . $elementAttributes['html'] . '</td>';
						} else {
							$formHtml .= "\n\t\t";
							if ($this->displayTitles) {
								$formHtml .= "<td class=\"title\">" . ($elementAttributes['title'] == '' ? '&nbsp;' : $elementAttributes['title']);
								if ($displayRestriction) {$formHtml .= "<br />\n\t\t\t<span class=\"restriction\">(" . ereg_replace ("\n", '<br />', $elementAttributes['restriction']) . ")</span>\n\t\t";}
								$formHtml .= '</td>';
							}
							$formHtml .= "\n\t\t<td class=\"data\">" . $elementAttributes['html'] . "</td>";
							if ($this->displayDescriptions) {$formHtml .= "\n\t\t<td class=\"description\">" . ($elementAttributes['description'] == '' ? '&nbsp;' : $elementAttributes['description']) . '</td>';}
						}
						$formHtml .= "\n\t</tr>";
				}
			}
		}
		
		# In the table mode, having compiled all the elements surround the elements with the table tag
		if ($this->display == 'tables') {$formHtml = "\n\n" . '<table summary="Online submission form">' . $formHtml . "\n</table>";}
		
		# Add in any hidden HTML, between the </table> and </form> tags
		$formHtml .= "\n<div id=\"hidden\">$hiddenHtml</div>";
		
		# Add the form button, either at the start or end as required
		$formButtonHtml = "\n\n" . '<p class="submit"><input value="' . $this->submitButtonText . (!empty ($this->submitButtonAccesskey) ? '&nbsp; &nbsp;[Alt+' . $this->submitButtonAccesskey . ']" accesskey="' . $this->submitButtonAccesskey : '') . '" type="submit" class="button" /></p>';
		$formHtml = ((!$this->submitButtonAtEnd) ? ($formButtonHtml . $formHtml) : ($formHtml . $formButtonHtml));
		
		# Add in the form HTML
		$html .= $formHtml;
		
		# Add in the reset button if wanted
		if ($this->resetButtonVisible) {$html .= "\n" . '<p class="reset"><input value="' . $this->resetButtonText . (!empty ($this->resetButtonAccesskey) ? '&nbsp; &nbsp;[Alt+' . $this->resetButtonAccesskey . ']" accesskey="' . $this->resetButtonAccesskey : '') . '" type="reset" class="resetbutton" /></p>';}
		
		# Add the required field indicator display message if required
		if ($this->requiredFieldIndicatorDisplay) {$html .= "\n" . '<p class="requiredmessage"><strong>*</strong> Items marked with an asterisk [*] are required fields and must be fully completed.</p>';}
		
		# Continue the HTML
		$html .= "\n\n" . '</form>';
		
		# Close the enclosing </div>
		$html .= "\n\n</div>";
		
		# Return the HTML
		return $html;
	}
	
	
	/**
	 * Function to prepare a problems list
	 * @access private
	 */
	function problemsList ($problems)
	{
		# Flatten the multi-level array of problems, starting first with the generic, top-level problems if any exist
		$problemsList = array ();
		if (isSet ($problems['generic'])) {
			foreach ($problems['generic'] as $elementName => $genericProblem) {
				$problemsList[] = $genericProblem;
			}
		}
		
		# Next, flatten the element-based problems, if any exist, starting with looping through each of the problems
		if (isSet ($problems['elements'])) {
			foreach ($problems['elements'] as $elementName => $elementProblems) {
				
				# Start an array of flattened element problems
				$currentElementProblemsList = array ();
				
				# Add each problem to the flattened array
				foreach ($elementProblems as $problemKey => $problemText) {
					$currentElementProblemsList[] = $problemText;
				}
				
				# If an item contains two or more errors, compile them and prefix them with introductory text
				$totalElementProblems = count ($elementProblems);
				$introductoryText = 'In the <strong>' . ($this->elements[$elementName]['title'] != '' ? $this->elements[$elementName]['title'] : ucfirst ($elementName)) . '</strong> section, ' . (($totalElementProblems > 1) ? "$totalElementProblems problems were" : 'a problem was') . ' found:';
				if ($totalElementProblems > 1) {
					$problemsList[] = application::showUserErrors ($currentElementProblemsList, $parentTabLevel = 2, $introductoryText, $nested = true);
				} else {
					
					# If there's just a single error for this element, carry the item through
					#!# Need to lcfirst the $problemtext here
					$problemsList[] = $introductoryText . ' ' . $problemText;
				}
			}
		}
		
		
		# If problems were found, construct a list
		$html = '';
		$totalProblems = count ($problemsList);
		if (($this->formPosted) && ($totalProblems > 0)) {
			$html = application::showUserErrors ($problemsList, $parentTabLevel = 0, ($totalProblems > 1 ? 'Various problems were' : 'A problem was') . ' found with the form information you submitted, as detailed below; please make the necessary corrections and re-submit the form:');
		}
		
		# Return the problems HTML (which may be an empty string)
		return $html;
	}
	
	
	/**
	 * Function to prepare completed form data; the data is assembled into a compiled version (e.g. in the case of checkboxes, separated by commas) and a component version (which is an array); in the case of scalars, the component version is set to be the same as the compiled version
	 * @access private
	 */
	function prepareData ()
	{
		# Loop through each element, whether submitted or not (otherwise gaps may be left, e.g. in the CSV writing)
		foreach ($this->elements as $elementName => $elementAttributes) {
			
			# Select the appropriate element type
			switch ($this->elements[$elementName]['type']) {
				
				case 'checkboxes':
					
					# Check whether the array is an associative array
					$valuesAreAssociativeArray = application::isAssociativeArray ($this->elements[$elementName]['valuesArray']);
					
					# For the component array, create an array with every defined element being assigned as itemName => boolean; checking is done against the available values rather than the posted values to prevent offsets
					foreach ($this->elements[$elementName]['valuesArray'] as $key => $value) {
						$submittableValue = ($valuesAreAssociativeArray ? $key : $value);
						$outputData[$elementName]['rawcomponents'][$submittableValue] = ($this->form[$elementName][$submittableValue] == 'true');
					}
					
					# Make an array of those items checked, starting with an empty array in case none are checked
					$checked = array ();
					$checkedPresented = array ();
					foreach ($outputData[$elementName]['rawcomponents'] as $key => $value) {
						if ($value) {
							$checked[] = $key;
							
							# For the presented version, substitute the index name with the presented name if the array is associative
							$checkedPresented[] = ($valuesAreAssociativeArray ? $this->elements[$elementName]['valuesArray'][$key] : $key);
						}
					}
					
					# Separate the compiled/presented items by a comma-newline
					$outputData[$elementName]['compiled'] = implode (",\n", $checked);
					$outputData[$elementName]['presented'] = implode (",\n", $checkedPresented);
					
					break;
					
				case 'datetime':
					
					# Map the components directly and assemble the elements into a string
					$outputData[$elementName]['rawcomponents'] = $this->form[$elementName];
					
					# Ensure there is a presented and a compiled version
					$outputData[$elementName]['presented'] = '';
					$outputData[$elementName]['compiled'] = '';
					
					# If all items are not empty then produce compiled and presented versions
					#!# This needs to be ALWAYS assigned in case $outputData[$elementName]['compiled'] and $outputData[$elementName]['presented'] are referred to later
					if (!application::allArrayElementsEmpty ($this->form[$elementName])) {
						
						# Make the compiled version be in SQL DATETIME format, i.e. YYYY-MM-DD HH:MM:SS
						$outputData[$elementName]['compiled'] = $this->form[$elementName]['year'] . '-' . $this->form[$elementName]['month'] . '-' . $this->form[$elementName]['day'] . (($this->elements[$elementName]['level'] == 'datetime') ? ' ' . $this->form[$elementName]['time'] : '');
						
						# Make the presented version in english text
						$outputData[$elementName]['presented'] = (($this->elements[$elementName]['level'] == 'datetime') ? $this->form[$elementName]['time'] . ', ': '') . date ('jS F, Y', mktime (0, 0, 0, $this->form[$elementName]['month'], $this->form[$elementName]['day'], $this->form[$elementName]['year']));
					}
					
					break;
					
				case 'email':					// Fallthrough
				case 'input':					// Fallthrough
					# For string datatypes, assign the data directly to the output array
					$outputData[$elementName]['presented'] = $this->form[$elementName];
					break;
					
				case 'textarea':
				case 'textareaMultipleY':
					# Assign the raw data directly to the output array
					$outputData[$elementName]['presented'] = $this->form[$elementName];
					
					# For the raw components version, split by the newline
					$outputData[$elementName]['rawcomponents'] = explode ("\n", $this->form[$elementName]);
					
					break;
					
				case 'richtext':
					# Clean the HTML
					$outputData[$elementName]['presented'] = $this->richtextClean ($this->form[$elementName]);
					break;
					
				case 'textareaMultipleXy':	// Fallthrough
					# Assign the raw data directly to the output array
					$outputData[$elementName]['presented'] = $this->form[$elementName];
					
					# For the raw components version, split by the newline then by the whitespace, presented as an array (x, y)
					$lines = explode ("\n", $this->form[$elementName]);
					foreach ($lines as $autonumber => $line) {
						list ($outputData[$elementName]['rawcomponents'][$autonumber]['x'], $outputData[$elementName]['rawcomponents'][$autonumber]['y']) = explode (' ', $line);
						ksort ($outputData[$elementName]['rawcomponents'][$autonumber]);
					}
					
					break;
					
				case 'radiobuttons':
					# Check whether the array is an associative array
					$valuesAreAssociativeArray = application::isAssociativeArray ($this->elements[$elementName]['valuesArray']);
					$submittableValues = ($valuesAreAssociativeArray ? array_keys ($this->elements[$elementName]['valuesArray']) : array_values ($this->elements[$elementName]['valuesArray']));
					
					# For the rawcomponents version, create an array with every defined element being assigned as itemName => boolean
					$outputData[$elementName]['rawcomponents'] = array ();
					foreach ($this->elements[$elementName]['valuesArray'] as $key => $value) {
						$submittableValue = ($valuesAreAssociativeArray ? $key : $value);
						if ($submittableValue == $this->nullText) {continue;}
						$outputData[$elementName]['rawcomponents'][$submittableValue] = ($this->form[$elementName] == $submittableValue);
					}
					
					# Take the selected option and ensure that this is in the array of available values
					#!# What if it's not? - This check should be moved up higher
					$outputData[$elementName]['compiled'] = (in_array ($this->form[$elementName], $submittableValues) ? $this->form[$elementName] : '');
					
					# For the presented version, substitute the visible text version used for the actual value if necessary
					$outputData[$elementName]['presented'] = (in_array ($this->form[$elementName], $submittableValues) ? ($valuesAreAssociativeArray ? $this->elements[$elementName]['valuesArray'][$this->form[$elementName]] : $this->form[$elementName]) : '');
					break;
					
				case 'heading':
					# Take no action for headings and cease further execution of the loop for this type
					continue 2;
					
				case 'hidden':
					# Map the components onto the array directly and assign the compiled version; no attempt is made to combine the data
					$outputData[$elementName]['rawcomponents'] = $this->form[$elementName];
					
					# The presented version is just an empty string
					$outputData[$elementName]['presented'] = '';
					break;
					
				case 'password':
					# Assign the (scalar) data directly to the output array
					$outputData[$elementName]['compiled'] = $this->form[$elementName];
					$outputData[$elementName]['presented'] = str_repeat ('*', strlen ($this->form[$elementName]));
					break;
					
				case 'select':
					# Check whether the array is an associative array
					$valuesAreAssociativeArray = application::isAssociativeArray ($this->elements[$elementName]['valuesArray']);
					
					# For the component array, loop through each defined element name and assign the boolean value for it
					foreach ($this->elements[$elementName]['valuesArray'] as $key => $value) {
						$submittableValue = ($valuesAreAssociativeArray ? $key : $value);
						$outputData[$elementName]['rawcomponents'][$submittableValue] = (in_array ($submittableValue, $this->form[$elementName]));
					}
					
					# For the compiled version, separate the compiled items by a comma-space
					$outputData[$elementName]['compiled'] = implode (",\n", $this->form[$elementName]);
					
					# For the presented version, substitute the visible text version used for the actual value if necessary
					#R# Can this be compbined with the compiled and the use of array_keys/array_values to simplify this?
					if (!$valuesAreAssociativeArray) {
						$outputData[$elementName]['presented'] = $outputData[$elementName]['compiled'];
					} else {
						
						$chosen = array ();
						foreach ($this->form[$elementName] as $key => $value) {
							if (isSet ($this->elements[$elementName]['valuesArray'][$value])) {
								$chosen[] = $this->elements[$elementName]['valuesArray'][$value];
							}
						}
						$outputData[$elementName]['presented'] = implode (",\n", $chosen);
					}
					break;
					
				case 'upload':
					# Perform the file upload and obtain arrays of failed and successful uploads
					list ($successes, $failures) = $this->performUpload ($elementName, $this->elements[$elementName]['uploadDirectory'], $this->elements[$elementName]['enableVersionControl']);
					
#!# There needs to be some way round about here of getting the raw array containing everything back, done in such a way that the CSV writing won't fail
					
					# Start the compiled result
					$outputData[$elementName]['presented'] = '';
					$outputData[$elementName]['compiled'] = array ();
					
					# If there were any succesful uploads, assign the compiled output
					if (!empty ($successes)) {
						
						# Add each of the files to the master array, appending the location for each
						foreach ($successes as $success => $attributes) {
							$filenames[] = $success;
							$outputData[$elementName]['compiled'][] = $this->elements[$elementName]['uploadDirectory'] . $success;
						}
						
						# For the compiled version, give the number of files uploaded and their names
						$totalSuccesses = count ($successes);
						$outputData[$elementName]['presented'] .= $totalSuccesses . ($totalSuccesses > 1 ? ' files' : ' file') . ' (' . implode (', ', $filenames) . ') ' . ($totalSuccesses > 1 ? 'were' : 'was') . ' successfully copied over.';
					}
					
					# If there were any failures, list them also
					if (!empty ($failures)) {
						$totalFailures = count ($failures);
						#!# ' ' being added even if there are no successes
						$outputData[$elementName]['presented'] .= ' ' . $totalFailures . ($totalFailures > 1 ? ' files' : ' file') . ' (' . implode (', ', $failures) . ') unfortunately failed to copy over for some unspecified reason.';
					}
					
					# The raw component array out with empty fields upto the number of created subfields
					$outputData[$elementName]['rawcomponents'] = array_pad ($outputData[$elementName]['compiled'], $this->elements[$elementName]['subfields'], false);
					
					break;
					
				# Default to throwing an internal error, as this should never happen
				default:
					#!# throwError;
			}
		}
		
		# Return the data
		return $outputData;
	}
	
	
	/**
	 * Function to check for problems
	 * @access private
	 */
	function checkForElementProblems ()
	{
		# Loop through each created form element (irrespective of whether it has been submitted or not), run checks for problems, and deal with multi-dimensional arrays
		foreach ($this->elements as $elementName => $elementAttributes) {
			
			# Check for specific problems which have been assigned in the per-element checks
			if ($this->elements[$elementName]['problems']) {
				
				# Assign the problem to the list of problems
				$this->elementProblems['elements'][$elementName] = $this->elements[$elementName]['problems'];
			}
			
			# Construct a list of required but incomplete fields
			if ($this->elements[$elementName]['requiredButEmpty']) {
				$incompleteFields[] = ($this->elements[$elementName]['title'] != '' ? $this->elements[$elementName]['title'] : ucfirst ($elementName));
			}
			
			#!# Do checks on hidden fields
		}
		
		# Check whether there are any incomplete fields 
		if (isSet ($incompleteFields)) {
			
			# If there are any incomplete fields, add it to the start of the problems array
			$this->elementProblems['generic']['incompleteFields'] = "You didn't enter a value for the following required " . ((count ($incompleteFields) == 1) ? 'field' : 'fields') . ': <strong>' . implode ('</strong>, <strong>', $incompleteFields) . '</strong>.';
			
		} else {
			# If there are no fields incomplete, remove the requirement to force upload(s) reselection
			if (isSet ($this->elementProblems['generic']['reselectUploads'])) {
				unset ($this->elementProblems['generic']['reselectUploads']);
			}
		}
		
		# Return a boolean of whether problems have been found or not
		return $problemsFound = (!empty ($this->elementProblems['generic'])) || (!empty ($this->elementProblems['elements']));
	}
	
	
	/**
	 * Function to output the data
	 * @access private
	 */
	function outputData ($outputType)
	{
		# Determine presentation format for each element
		$this->mergeInPresentationDefaults ();
		
		# Assign the presented data according to the output type
		foreach ($this->outputData as $elementName => $data) {
			$presentedData[$elementName] = $data[$this->elements[$elementName]['outputFormat'][$outputType]];
		}
		
		# Select the required output method
		switch ($outputType) {
			
			# Return the results as a raw, uncompiled data array
			case 'processing':
				return $this->outputDataProcessing ($presentedData);
				
			# Dump the results to the screen
			case 'screen':
				echo $this->outputDataScreen ($presentedData);
				break;
				
			# E-mail the data to the administrator and/or the submitter
			case 'email':	// Fall through
			case 'confirmationEmail':
				$this->outputDataEmailed ($presentedData, $outputType);
				break;
				
			# Write the data to a CSV file
			case 'file':
				$this->outputDataFile ($presentedData);
				break;
				
			#!# Need to throw a programming error here
			default:
		}
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
			
			# If the presentation matrix has a specification for the element (only heading should not do so), merge the setup-assigned output formats over the defaults in the presentation matrix
			if (isSet ($presentationDefaults[$attributes['type']])) {
				$this->elements[$element]['outputFormat'] = array_merge ($presentationDefaults[$attributes['type']], $attributes['outputFormat']);
			}
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
					'rawcomponents'	=> 'Array with every defined element being assigned as itemName => boolean true/false',
					'compiled'		=> 'String of checked items only as selectedItemName1\n,selectedItemName2\n,selectedItemName3',
					'presented'		=> 'As compiled, but in the case of an associative array of values being supplied as selectable items, the visible text version used instead of the actual value',
				),
				'file'				=> array ('rawcomponents', 'compiled', 'presented'),
				'email'				=> array ('compiled', 'rawcomponents', 'presented'),
				'confirmationEmail'	=> array ('presented', 'compiled'),
				'screen'			=> array ('presented', 'compiled'),
				'processing'		=> array ('rawcomponents', 'compiled', 'presented'),
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
			),
			
			'radiobuttons' => array (
				'_descriptions' => array (
					'rawcomponents'	=> 'An array with every defined element being assigned as itemName => boolean',
					'compiled'		=> 'The (single) chosen item, if any',
					'presented'		=> 'As compiled, but in the case of an associative array of values being supplied as selectable items, the visible text version used instead of the actual value',
				),
				'file'				=> array ('compiled', 'rawcomponents', 'presented'),
				'email'				=> array ('compiled', 'rawcomponents', 'presented'),
				'confirmationEmail'	=> array ('presented', 'compiled'),
				'screen'			=> array ('presented', 'compiled'),
				'processing'		=> array ('compiled', 'rawcomponents', 'presented'),
			),
			
			'select' => array (
				'_descriptions' => array (
					'rawcomponents'	=> 'An array with every defined element being assigned as itemName => boolean',
					'compiled'		=> 'String of checked items only as selectedItemName1\n,selectedItemName2\n,selectedItemName3',
					'presented'		=> 'As compiled, but in the case of an associative array of values being supplied as selectable items, the visible text version used instead of the actual value',
				),
				#!# Ideally, when multiple is false, the default for file would become 'compiled'; this would have to be done by merging presented and compiled (making them unselectable) and using the spare as a modified copy of rawcomponents
				'file'				=> array ('compiled', 'presented', 'rawcomponents'),
				'email'				=> array ('compiled', 'presented', 'rawcomponents'),
				'confirmationEmail'	=> array ('presented', 'compiled'),
				'screen'			=> array ('presented', 'compiled'),
				'processing'		=> array ('rawcomponents', 'compiled', 'presented'),
			),
			
			'textarea' => array (
				'_descriptions' => array (
					'presented'		=> 'Show as unaltered string',
					'rawcomponents'	=> 'An array with every line being assigned as linenumber => string',
				),
				'file'				=> array ('presented'), #, 'compiled'
				'email'				=> array ('presented'), #, 'compiled'
				'confirmationEmail'	=> array ('presented'), #, 'compiled'
				'screen'			=> array ('presented'), #, 'compiled'
				'processing'		=> array ('presented', 'rawcomponents'), #, 'compiled'
			),
			
			'richtext' => array (
				'_descriptions' => array (
					'presented'		=> 'Show as unaltered string',
				),
				'file'				=> array ('presented'),
				'email'				=> array ('presented'),
				'confirmationEmail'	=> array ('presented'),
				'screen'			=> array ('presented'),
				'processing'		=> array ('presented'),
			),
			
			'textareaMultipleY' => array (
				'_descriptions' => array (
					'presented'		=> 'Show as unaltered string',
					'rawcomponents'	=> 'An array with every line being assigned as linenumber => string',
				),
				'file'				=> array ('presented'), #, 'compiled'
				'email'				=> array ('presented'), #, 'compiled'
				'confirmationEmail'	=> array ('presented'), #, 'compiled'
				'screen'			=> array ('presented'), #, 'compiled'
				'processing'		=> array ('rawcomponents', 'presented'), #, 'compiled'
			),
			
			'textareaMultipleXy' => array (
				'_descriptions' => array (
					'presented'		=> 'Show as unaltered string',
					'rawcomponents'	=> 'An array with every line being assigned as linenumber => array (x => string, y => string)',
				),
				'file'				=> array ('presented'), #, 'compiled'
				'email'				=> array ('presented'), #, 'compiled'
				'confirmationEmail'	=> array ('presented'), #, 'compiled'
				'screen'			=> array ('presented'), #, 'compiled'
				'processing'		=> array ('rawcomponents', 'presented'), #, 'compiled'
			),
			
			'upload' => array (
				'_descriptions' => array (
					'rawcomponents'	=> 'An array with every defined element being assigned as autonumber => filename',
					'compiled'		=> 'An array with every successful element being assigned as autonumber => filename',
					'presented'		=> 'Submitted files (and failed uploads) as a human-readable string with the original filenames in brackets',
				),
				'file'				=> array ('rawcomponents', 'presented'),
				'email'				=> array ('presented', 'compiled'),
				'confirmationEmail'	=> array ('presented'),
				'screen'			=> array ('presented'),
				'processing'		=> array ('compiled', 'rawcomponents', 'presented'),
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
	function showPresentationMatrix ()
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
				$html .= "\n\t" . "<li><strong>$descriptor</strong>: " . htmlentities ($description) . "</li>";
			}
			$html .= "\n" . '</ul>';
			
			# Start the table of presentation formats, laid out in a table with headings
			$html .= "\n" . '<table class="documentation">';
			$html .= "\n\t" . '<tr>';
			$html .= "\n\t\t" . '<th class="displayformat">Display format</th>';
			$html .= "\n\t\t" . '<th>Default output type</th>';
			$html .= "\n\t\t" . '<th>Others permissable</th>';
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
				$html .= "\n\t\t" . "<td class=\"defaultdisplayformat\"><strong>$default</strong><!-- [" . htmlentities ($presentationMatrix[$type]['_descriptions'][$default]) . ']--></td>';
				$html .= "\n\t\t" . "<td>$others</td>";
				$html .= "\n\t" . '</tr>';
			}
			$html .= "\n" . '</table>';
		}
		
		# Show the result
		echo $html;
	}
	
	
	/**
	 * Function to return the output data as an array
	 * @access private
	 */
	function outputDataProcessing ($presentedData)
	{
		# Return the raw, uncompiled data
		return $presentedData;
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
		
		# Open an enclosing <div> for stylesheet hooking
		$html  = "\n" . '<div class="ultimateform">';
		
		# Introduce the table
		$html .= "\n\n" . '<p class="success">The information submitted is confirmed as:</p>';
		$html .= "\n" . '<table class="results" summary="Table of results">';
		
		# Assemble the HTML, convert newlines to breaks (without a newline in the HTML), tabs to four spaces, and convert HTML entities
		foreach ($presentedData as $elementName => $data) {
			
			# Remove empty elements from display
			if (empty ($data)) {continue;}
			
			/*
			# For associative select types, substitute the submitted value with the the visible value
			#!# PATCHED IN 041201; This needs to be applied to other select types and to dealt with generically in the processing stage; also, should this be made configurable, or is it assumed that the visible version is always wanted for the confirmation screen?
			if ($this->elements[$elementName]['type'] == 'select') {
				if (application::isAssociativeArray ($this->elements[$elementName]['valuesArray'])) {
					foreach ($this->form[$elementName] as $key => $value) {
						$data[$key] = $this->form[$elementName]['valuesArray'][$data[$key]];
					}
				}
			}
			*/
			
			# If the data is an array, convert the data to a printable representation of the array
			if (is_array ($data)) {$data = application::printArray ($data);}
			
			# Compile the HTML
			$html .= "\n\t<tr>";
			$html .= "\n\t\t" . '<td class="key">' . (isSet ($this->elements[$elementName]['title']) ? $this->elements[$elementName]['title'] : $elementName) . ':</td>';
			$html .= "\n\t\t" . '<td class="value">' . str_replace (array ("\n", "\t"), array ('<br />', str_repeat ('&nbsp;', 4)), htmlentities ($data)) . '</td>';
			$html .= "\n\t</tr>";
		}
		$html .= "\n" . '</table>';
		
		# Close the enclosing </div>
		$html .= "\n\n</div>";
		
		# Return the constructed HTML
		return $html;
	}
	
	
	/**
	 * Function to output the data via e-mail
	 * @access private
	 */
	function outputDataEmailed ($presentedData, $outputType)
	{
		# If, for the confirmation type, a confirmation address has not been assigned, say so and take no further action
		#!# This should be moved up so that a confirmation e-mail widget is a required field
		if ($outputType == 'confirmationEmail') {
			if (empty ($this->configureResultConfirmationEmailRecipient)) {
				echo "\n\n" . '<p class="error">A confirmation e-mail could not be sent as no address was given.</p>';
				return false;
			}
		}
		
		# Construct the introductory text, including the IP address for the e-mail type
		#!# Make the IP address bit configurable; currently removed: ($outputType == 'email' ? ', from the IP address ' . $_SERVER['REMOTE_ADDR'] : '')
		$introductoryText = ($outputType == 'email' ? 'Below is a submission from the form' :  'Below is a confirmation of (apparently) your submission from the form') . " at \n" . $_SERVER['_PAGE_URL'] . "\nmade at " . date ('g:ia, jS F Y') . ', from the IP address ' . $_SERVER['REMOTE_ADDR'] . '.';
		
		# Add an abuse notice if required
		if (($outputType == 'confirmationEmail') && ($this->configureResultConfirmationEmailAbuseNotice)) {$introductoryText .= "\n\n(If it was not you who submitted the form, please report it as abuse to " . $this->configureResultConfirmationEmailAdministrator . ' .)';}
		
		# If nothing has been submitted, return the result directly
		if (application::allArrayElementsEmpty ($presentedData)) {
			$resultLines[] = 'No information' . ($this->hiddenElementPresent ? ', other than any hidden data, ' : '') . ' was submitted.';
		} else {
			
			# Assemble a master array of e-mail text, adding the real element name if it's the result rather than confirmation e-mail type. NB: this used to be using str_pad in order to right-align the names, but it doesn't look all that neat in practice: str_pad ($this->elements[$elementName]['title'], ($this->longestKeyNameLength ($this->outputData) + 1), ' ', STR_PAD_LEFT) . ': ' . $presentedData
			foreach ($presentedData as $elementName => $data) {
				
				# Remove empty elements from display
				#!# Make this a hook
				if (empty ($data)) {continue;}
				
				# If the data is an array, convert the data to a printable representation of the array
				if (is_array ($presentedData[$elementName])) {$presentedData[$elementName] = application::printArray ($presentedData[$elementName]);}
				
				# Compile the result line
				$resultLines[] = strip_tags ($this->elements[$elementName]['title']) . ($outputType == 'email' ? " [$elementName]" : '') . ":\n" . $presentedData[$elementName];
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
		$additionalHeaders  = 'From: Website feedback <' . ($outputType == 'email' ? $this->configureResultEmailAdministrator : $this->configureResultConfirmationEmailAdministrator) . ">\r\n";
		if (isSet ($this->configureResultEmailCc)) {$additionalHeaders .= 'Cc: ' . implode (', ', $this->configureResultEmailCc) . "\r\n";}
		
		# Add the reply-to if it is set and is not empty and that it has been completed (e.g. in the case of a non-required field)
		if (isSet ($this->configureResultEmailReplyTo)) {
			if ($this->configureResultEmailReplyTo) {
				if (application::validEmail ($this->outputData[$this->configureResultEmailReplyTo]['presented'])) {
					$additionalHeaders .= 'Reply-To: ' . $this->outputData[$this->configureResultEmailReplyTo]['presented'] . "\r\n";
				}
			}
		}
		
		# Send the e-mail
		#!# Add an @ and a message if sending fails (marking whether the info has been logged in other ways)
		$success = mail (
			$recipient,
			$this->configureResultEmailedSubjectTitle[$outputType],
			wordwrap ($introductoryText . "\n\n\n\n" . implode ("\n\n\n", $resultLines)),
			$additionalHeaders
		);
		
		# Confirm sending (or an error) for the confirmation e-mail type
		if ($outputType == 'confirmationEmail') {
			echo "\n\n" . '<p class="' . ($success ? 'success' : 'error') . '">' . ($success ? 'A confirmation e-mail has been sent' : 'There was a problem sending a confirmation e-mail') . ' to the address you gave (' . 					$presentedData[$elementName] = str_replace ('@', '<span>&#64;</span>', htmlentities ($this->configureResultConfirmationEmailRecipient)) . ').</p>';
		}
	}
	
	
	/**
	 * Function to write the results to a CSV file
	 * @access private
	 */
	function outputDataFile ($presentedData)
	{
		# Assemble the data into CSV format
		list ($headerLine, $dataLine) = application::arrayToCsv ($presentedData);
		
		# Compile the data, adding in the header if the file doesn't already exist, and writing a newline after each line
		$data = (filesize ($this->configureResultFileFilename) == 0 ? $headerLine : '') . $dataLine;
		
		#!# A check is needed to ensure the file being written to doesn't previously contain headings related to a different configuration
		
		# Write the data or handle the error
		if (!application::writeDataToFile ($data, $this->configureResultFileFilename)) {
			echo "\n\n" . '<p class="error">There was a problem writing the information you submitted to a file. It is likely this problem is temporary - please wait a short while then press the refresh button.</p>';
		}
	}
	
	
	/**
	 * Function to perform the file uploading
	 * @access private
	 */
	function performUpload ($elementName, $uploadDirectory, $enableVersionControl = true)
	{
		# Start arrays to hold a list of successes and failures
		$successes = array ();
		$failures = array ();
		
		# If elements have been uploaded, process them
		if (isSet ($this->form[$elementName])) {
			
			# Loop through each apparently uploaded file; if a file is included
			foreach ($this->form[$elementName] as $key => $attributes) {
				
				# Assign the eventual name (overwriting the uploaded name if the name is being forced)
				#!# How can we deal with multiple files?
				if ($this->elements[$elementName]['forcedFileName']) {
					#!# This is very hacky
					$attributes['name'] = $_FILES[$this->formName]['name'][$elementName][$key] = $this->elements[$elementName]['forcedFileName'];
				}
				
				# Check whether a file already exists
				if (file_exists ($existingFileName = ($uploadDirectory . $_FILES[$this->formName]['name'][$elementName][$key]))) {
					
					# Check whether the file being uploaded has the same checksum as the existing file
					if (md5_file ($existingFileName) != md5_file ($_FILES[$this->formName]['tmp_name'][$elementName][$key])) {
						
						# If version control is enabled, move the old file, appending the date; if the file really cannot be renamed, append the date to the new file instead
						if ($enableVersionControl) {
							$timestamp = date ('Ymd-Hms');
							if (!@rename ($existingFileName, $existingFileName . '.replaced-' . $timestamp)) {
								$_FILES[$this->formName]['name'][$elementName][$key] .= '.forRenamingBecauseCannotMoveOld-' . $timestamp;
							}
							
						/* # If version control is not enabled, give a new name to the new file to prevent the old one being overwritten accidentally
						} else {
							# If a file of the same name but a different checksum exists, append the date and time to the proposed filename
							$_FILES[$this->formName]['name'][$elementName][$key] .= date ('.Ymd-Hms');
							*/
						}
					}
				}
				
				# Attempt to upload the file
				if (!move_uploaded_file ($_FILES[$this->formName]['tmp_name'][$elementName][$key], $uploadDirectory . $_FILES[$this->formName]['name'][$elementName][$key])) {
					# Create an array of any failed file uploads
					#!# Not sure what happens if this fails, given that the attributes may not exist
					$failures[$attributes['name']] = $attributes;
					
				# Continue if the file upload attempt was successful
				} else {
					# Create an array of any successful file uploads. For security reasons, if the filename is modified to prevent accidental overwrites, the original filename is not modified here
					#!# There needs to be a differential between presented and actual data in cases where a different filename is actually written to the disk
					$successes[$attributes['name']] = $attributes;
				}
			}
		}
		
		# Return the result
		return array ($successes, $failures);
	}
}


#!# Make the file specification of the form more user-friendly (e.g. specify / or ./ options)
#!# Do a single error check that the number of posted elements matches the number defined; this is useful for checking that e.g. hidden fields are being posted
#!# Need to add basic protection for ensuring that form sub-elements submitted (in selectable types) are in the list of available values; this has already been achieved for checkboxes, relatively easily
#!# Add form setup checking validate input types like cols= is numeric, etc.
#!# Add a warnings flag in the style of the errors flagging to warn of changes which have been made silently
#!# Need to add configurable option (enabled by default) to add headings to new CSV when created
#!# Consider a flag (enabled by default) to use hidden data internally rather than the posted version
#!# Ideally add a catch to prevent the same text appearing twice in the errors box (e.g. two widgets with "details" as the descriptive text)
#!# Input type="image" like submit but note http://www.blooberry.com/indexdot/html/tagpages/i/inputimage.htm has co-ordinates as well
#!# Throw a 'fake input' error if the number of files uploaded is greater than the number of form elements
#!# Convert the e-mail widget type to be a stub with a regexp on the normal input type
#!# Enable maximums as well as the existing minimums
#!# Complete the restriction notices
#!# Add a CSS class to each type of widget so that more detailed styling can be applied
#!# Enable locales, e.g. ordering month-date-year for US users
#!# Consider language localisation (put error messages into a global array)
#!# Add in <span>&#64;</span> for on-screen e-mail types
#!# Add standalone database-writing
#!# Apache setup needs to be carefully tested, in conjunction with php.net/ini-set and php.net/configuration.changes
#!# Add links to the id="$elementName" form elements in cases of USER errors
#!# Need to prevent the form code itself being overwritable by uploads... (is that possible to ensure?)
#!# Add POST security for hidden fields


# Version 2 feature proposals
#!# Full object orientation - change the form into a package of objects
#!#		Change each input type to an object, with a series of possible checks that can be implemented - class within a class?
#!# 	Change the output methods to objects
#!# Allow multiple carry-throughs, perhaps using formCarried[$formNumber][...]: Add carry-through as an additional array section; then translate the additional array as a this-> input to hidden fields.
#!# Enable javascript as an option
#!# 	Use ideas in http://www.sitepoint.com/article/1273/3 for having js-validation with an icon
#!# 	Style like in http://www.sitepoint.com/examples/simpletricks/form-demo.html [linked from http://www.sitepoint.com/article/1273/3]


?>