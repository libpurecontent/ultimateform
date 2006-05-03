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
 * @copyright Copyright © 2003-6, Martin Lucas-Smith, University of Cambridge
 * @version 1.0.2
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
	var $name;									// The name of the form
	var $location;								// The location where the form is submitted to
	var $duplicatedElementNames = array ();		// The array to hold any duplicated form field names
	var $formSetupErrors = array ();			// Array of form setup errors, to which any problems can be added
	var $elementProblems = array ();			// Array of submitted element problems
	
	# State control
	var $formPosted;							// Flag for whether the form has been posted
	var $formDisplayed = false;					// Flag for whether the form has been displayed
	var $setupOk = false;					// Flag for whether the form has been set up OK
	var $headingTextCounter = 1;				// Counter to enable uniquely-named fields for non-form elements (i.e. headings), starting at 1 #!# Get rid of this somehow
	var $uploadProperties;						// Data store to cache upload properties if the form contains upload fields
	var $hiddenElementPresent = false;			// Flag for whether the form includes one or more hidden elements
	
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
	var $minimumPhpVersion = 4.3;	// md5_file requires 4.2+; file_get_contents is 4.3+
	var $escapeCharacter = "'";		// Character used for escaping of output	#!# Currently ignored in derived code
	
	# Specify available arguments as defaults or as NULL (to represent a required argument)
	var $argumentDefaults = array (
		'name'								=> 'form',							# Name of the form
		'div'								=> 'ultimateform',					# The value of <div class=""> which surrounds the entire output (or false for none)
		'displayPresentationMatrix'			=> false,							# Whether to show the presentation defaults
		'displayTitles'						=> true,							# Whether to show user-supplied titles for each widget
		'displayDescriptions'				=> true,							# Whether to show user-supplied descriptions for each widget
		'displayRestrictions'				=> true,							# Whether to show/hide restriction guidelines
		'display'							=> 'tables',						# Whether to display the form using 'tables', 'css' (CSS layout) 'paragraphs' or 'template'
		'displayTemplate'					=> '',								# Either a filename or a (long) string containing placemarkers
		'displayTemplatePatternWidget'		=> '{%element}',					# The pattern used for signifying element name widget positions when templating
		'displayTemplatePatternLabel'		=> '{[%element]}',					# The pattern used for signifying element name label positions (optional) when templating
		'displayTemplatePatternSpecial'		=> '{[[%element]]}',				# The pattern used for signifying element name special item positions (e.g. submit, reset, problems) when templating
		'debug'								=> false,							# Whether to switch on debugging
		'developmentEnvironment'			=> false,							# Whether to run in development mode
		'displayColons'						=> true,							# Whether to show colons after the initial description
		'whiteSpaceTrimSurrounding'			=> true,							# Whether to trim surrounding white space in any forms which are submitted
		'whiteSpaceCheatAllowed'			=> false,							# Whether to allow people to cheat submitting whitespace only in required fields
		'formCompleteText'					=> 'Many thanks for your input.',	# The form completion text (or false if not to display it at all)
		'submitButtonAtEnd'					=> true,							# Whether the submit button appears at the end or the start of the form
		'submitButtonText'					=> 'Submit!',						# The form submit button text
		'submitButtonAccesskey'				=> 's',								# The form submit button accesskey
		'submitButtonImage'					=> false,							# Location of an image to replace the form submit button
		'resetButton'						=> false,							# Whether the reset button is visible (note that this must be switched on in the template mode to appear, even if the reset placemarker is given)
		'resetButtonText'					=> 'Clear changes',					# The form reset button
		'resetButtonAccesskey'				=> 'r',								# The form reset button accesskey
		'warningMessage'					=> false,							# The form incompletion message (a specialised default is used)
		'requiredFieldIndicator'			=> true,							# Whether the required field indicator is to be displayed (top / bottom/true / false) (note that this must be switched on in the template mode to appear, even if the reset placemarker is given)
		'requiredFieldClass'				=> 'required',						# The CSS class used to mark a widget as required
		'submitTo'							=> false,							# The form processing location if being overriden
		'autoCenturyConversionEnabled'		=> true,							# Whether years entered as two digits should automatically be converted to four
		'autoCenturyConversionLastYear'		=> 69,								# The last two figures of the last year where '20' is automatically prepended
		'nullText'							=> 'Please select',					# The 'null' text for e.g. selection boxes
		'opening'							=> false,							# Optional starting datetime as an SQL string
		'closing'							=> false,							# Optional closing datetime as an SQL string
		'validUsers'						=> false,							# Optional valid user(s) - if this is set, a user will be required. To set, specify string/array of valid user(s), or '*' to require any user
		'user'								=> false,							# Explicitly-supplied username (if none specified, will check for REMOTE_USER being set
		'userKey'							=> false,							# Whether to log the username, as the key
		'loggedUserUnique'					=> false,							# Run in user-uniqueness mode, making the key of any CSV the username and checking for resubmissions
		'timestamping'						=> false,							# Add a timestamp to any CSV entry
		'escapeOutput'						=> false,							# Whether to escape output in the processing output ONLY (will not affect other types)
		'emailIntroductoryText'				=> '',								# Introductory text for e-mail output type
		'confirmationEmailIntroductoryText'	=> '',								# Introductory text for confirmation e-mail output type
		'callback'							=> false,							# Callback function (string name) (NB cannot be $this->methodname) with one integer parameter, so be called just before emitting form HTML - -1 is errors on form, 0 is blank form, 1 is result presentation if any (not called at all if form not displayed)
	);
	
	
	## Load initial state and assign settings ##
	
	/**
	 * Constructor
	 * @param array $arguments Settings
	 */
	function form ($suppliedArguments = array ())
	{
		# Load the application support library which itself requires the pureContent framework file, pureContent.php; this will clean up $_SERVER
		require_once ('application.php');
		
		# Assign constants
		$this->timestamp = date ('Y-m-d H:m:s');
		
		# Import supplied arguments to assign defaults against specified ones available
		foreach ($this->argumentDefaults as $argument => $defaultValue) {
			$this->settings[$argument] = (isSet ($suppliedArguments[$argument]) ? $suppliedArguments[$argument] : $defaultValue);
		}
		
		# Define the submission location (as _SERVER cannot be set in a class variable declaration); PATH_INFO attacks (see: http://forum.hardened-php.net/viewtopic.php?id=20 ) are not relevant here for this form usage
		if ($this->settings['submitTo'] === false) {$this->settings['submitTo'] = $_SERVER['REQUEST_URI'];}
		
		# Ensure the userlist is an array, whether empty or otherwise
		$this->settings['validUsers'] = application::ensureArray ($this->settings['validUsers']);
		
		# If no user is supplied, attempt to obtain the REMOTE_USER (if one exists) as the default
		if (!$this->settings['user']) {$this->settings['user'] = (isSet ($_SERVER['REMOTE_USER']) ? $_SERVER['REMOTE_USER'] : false);}
		
		# If there are files posted, merge the multi-part posting of files to the main form array, effectively emulating the structure of a standard form input type
		if (!empty ($_FILES[$this->settings['name']])) {$this->mergeFilesIntoPost ();}
		
		# Assign whether the form has been posted or not
		$this->formPosted = (isSet ($_POST[$this->settings['name']]));
		
		# Add in the hidden security fields if required, having verified username existence if relevant; these need to go at the start so that any username is set as the key
		$this->addHiddenSecurityFields ();
		
		# Import the posted data if the form is posted; this has to be done initially otherwise the input widgets won't have anything to reference
		if ($this->formPosted) {$this->form = $_POST[$this->settings['name']];}
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
			'output'				=> array (),# Presentation format
			'required'				=> false,	# Whether required or not
			'enforceNumeric'		=> false,	# Whether to enforce numeric input or not (optional; defaults to false) [ignored for e-mail type]
			'size'					=> 30,		# Visible size (optional; defaults to 30)
			'maxlength'				=> '',		# Maximum length (optional; defaults to no limit)
			'default'				=> '',		# Default value (optional)
			'regexp'				=> '',		# Regular expression against which the submission must validate
			'disallow'				=> '',		# Regular expression against which the submission must not validate
		);
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, $functionName);
		
		$arguments = $widget->getArguments ();
		
		# If the widget is not editable, fix the form value to the default
		if (!$arguments['editable']) {$this->form[$arguments['name']] = $arguments['default'];}
		
		# Obtain the value of the form submission (which may be empty)
		$widget->setValue (isSet ($this->form[$arguments['name']]) ? $this->form[$arguments['name']] : '');
		
		# Handle whitespace issues
		$widget->handleWhiteSpace ();
		
		# Run maxlength checking
		$widget->checkMaxLength ();
		
		# Clean to numeric if required
		$widget->cleanToNumeric ();
		
		# Perform pattern checks
		$widget->regexpCheck ();
		
		
		$elementValue = $widget->getValue ();
		
		
		# Assign the initial value if the form is not posted (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid)
		if (!$this->formPosted) {$elementValue = $arguments['default'];}
		
		# Describe restrictions on the widget
		if ($arguments['enforceNumeric'] && ($functionName != 'email')) {$restriction = 'Must be numeric';}
		if ($functionName == 'email') {$restriction = 'Must be valid';}
		if ($arguments['regexp'] && ($functionName != 'email')) {$restriction = 'A specific pattern is required';}
		
		
		# Re-assign back the value
		$this->form[$arguments['name']] = $elementValue;
		
		# Define the widget's core HTML
		if ($arguments['editable']) {
			$widgetHtml = '<input name="' . $this->settings['name'] . "[{$arguments['name']}]\" type=\"" . ($functionName == 'password' ? 'password' : 'text') . "\" size=\"{$arguments['size']}\"" . ($arguments['maxlength'] != '' ? " maxlength=\"{$arguments['maxlength']}\"" : '') . " value=\"" . htmlspecialchars ($this->form[$arguments['name']]) . '" />';
		} else {
			$widgetHtml  = ($functionName == 'password' ? str_repeat ('*', strlen ($arguments['default'])) : htmlspecialchars ($this->form[$arguments['name']]));
			#!# Change to registering hidden internally
			$widgetHtml .= '<input name="' . $this->settings['name'] . "[{$arguments['name']}]\" type=\"hidden\" value=\"" . htmlspecialchars ($this->form[$arguments['name']]) . '" />';
		}
		
		# Get the posted data
		if ($this->formPosted) {
			if ($functionName == 'password') {
				$data['compiled'] = $this->form[$arguments['name']];
				$data['presented'] = str_repeat ('*', strlen ($this->form[$arguments['name']]));
			} else {
				$data['presented'] = $this->form[$arguments['name']];
			}
		}
		
		# Add the widget to the master array for eventual processing
		$this->elements[$arguments['name']] = array (
			'type' => $functionName,
			'html' => $widgetHtml,
			'title' => $arguments['title'],
			'description' => $arguments['description'],
			'restriction' => (isSet ($restriction) && $arguments['editable'] ? $restriction : false),
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $arguments['required'],
			'requiredButEmpty' => $widget->requiredButEmpty (),
			'suitableAsEmailTarget' => ($functionName == 'email'),
			'output' => $arguments['output'],
			'data' => (isSet ($data) ? $data : NULL),
			'datatype' => "`{$arguments['name']}` " . 'VARCHAR(' . ($arguments['maxlength'] ? $arguments['maxlength'] : '255') . ')' . ($arguments['required'] ? ' NOT NULL' : '') . " COMMENT '" . (addslashes ($arguments['title'])) . "'",
		);
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
			'output'				=> array (),# Presentation format
			'required'				=> false,	# Whether required or not
			'enforceNumeric'		=> false,	# Whether to enforce numeric input or not (optional; defaults to false)
			'cols'					=> 30,		# Number of columns (optional; defaults to 30)
			'rows'					=> 5,		# Number of rows (optional; defaults to 30)
			'default'				=> '',		# Default value (optional)
			'regexp'				=> '',		# Regular expression(s) against which all lines of the submission must validate
			'disallow'				=> '',		# Regular expression against which all lines of the submission must not validate
			'mode'					=> 'normal',	# Special mode: normal/lines/coordinates
			'editable'				=> true,	# Whether the widget is editable (if not, a hidden element will be substituted but the value displayed)
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
		
		# Check whether the field satisfies any requirement for a field to be required
		$requiredButEmpty = $widget->requiredButEmpty ();
		
		$elementValue = $widget->getValue ();
		
		# Perform validity tests if anything has been submitted and regexp(s)/disallow are supplied
		if ($elementValue && ($arguments['regexp'] || $arguments['disallow'] || $arguments['mode'] == 'coordinates')) {
			
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
				if ($arguments['regexp']) {
					if (!ereg ($arguments['regexp'], $line)) {
						$problemLines[] = $i;
						continue;
					}
				}
				
				# If the line does not validate against a specified regexp, add the line to a list of lines containing a problem then move onto the next line
				if ($arguments['disallow']) {
					if (ereg ($arguments['disallow'], $line)) {
						$disallowProblemLines[] = $i;
						continue;
					}
				}
			}
			
			# If any problem lines are found, construct the error message for this
			if (isSet ($problemLines)) {
				$elementProblems['failsRegexp'] = (count ($problemLines) > 1 ? 'Rows ' : 'Row ') . implode (', ', $problemLines) . (count ($problemLines) > 1 ? ' do not' : ' does not') . ' match a specified pattern required for this section' . (($arguments['mode'] == 'coordinates') ? ', ' . ((count ($arguments['regexp']) > 1) ? 'including' : 'namely' ) . ' the need for two co-ordinates per line' : '') . '.';
			}
			
			# If any problem lines are found, construct the error message for this
			if (isSet ($disallowProblemLines)) {
				$elementProblems['failsDisallow'] = (count ($disallowProblemLines) > 1 ? 'Rows ' : 'Row ') . implode (', ', $disallowProblemLines) . (count ($disallowProblemLines) > 1 ? ' match' : ' matches') . ' a specified disallowed pattern for this section.';
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
			$widgetHtml = '<textarea name="' . $this->settings['name'] . "[{$arguments['name']}]\" id=\"" . $this->settings['name'] . $this->cleanId ("[{$arguments['name']}]") . "\" cols=\"{$arguments['cols']}\" rows=\"{$arguments['rows']}\">" . htmlspecialchars ($this->form[$arguments['name']]) . '</textarea>';
		} else {
			$widgetHtml  = str_replace ("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', nl2br (htmlspecialchars ($this->form[$arguments['name']])));
			$widgetHtml .= '<input name="' . $this->settings['name'] . "[{$arguments['name']}]\" type=\"hidden\" value=\"" . htmlspecialchars ($this->form[$arguments['name']]) . '" />';
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
			'html' => $widgetHtml,
			'title' => $arguments['title'],
			'description' => $arguments['description'],
			'restriction' => (isSet ($restriction) && $arguments['editable'] ? $restriction : false),
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $arguments['required'],
			'requiredButEmpty' => $requiredButEmpty,
			'suitableAsEmailTarget' => false,
			'output' => $arguments['output'],
			'data' => (isSet ($data) ? $data : NULL),
			'datatype' => "`{$arguments['name']}` " . 'BLOB' . ($arguments['required'] ? ' NOT NULL' : '') . " COMMENT '" . (addslashes ($arguments['title'])) . "'",
		);
	}
	
	
	/**
	 * Create a rich text editor field based on FCKeditor 2.2
	 * @param array $arguments Supplied arguments - see template
	 */
	 
	/*
	
	# Note: make sure php_value file_uploads is on in the upload location!
	
	The following source code alterations must be made to FCKeditor 2.2
	
	1. Customised configurations which cannot go in the PHP at present
	Add the supplied file /_fckeditor/fckconfig-customised.js
	
	2. Add in main fckconfig.js (NOT elsewhere) after DocType [see http://sourceforge.net/tracker/index.php?func=detail&aid=1199631&group_id=75348&atid=543653  and  https://sourceforge.net/tracker/index.php?func=detail&aid=1200670&group_id=75348&atid=543653 ]
	// Prevent left-right scrollbars
	FCKConfig.DocType = '' ;
	
	3. Open editor/filemanager/browser/default/connectors/php/config.php and change:
	$Config['Enabled'] = true ;
	$Config['UserFilesPath'] = '/' ;
	$Config['UserFilesAbsolutePath'] = $_SERVER['DOCUMENT_ROOT'];
	
	4. In editor/filemanager/browser/default/connectors/php/io.php: add at the start of GetUrlFromPath() and ServerMapFolder() the lines:
	#MLS# Don't differentiate locations based on the resource type
	$resourceType = '';
	
	5. In editor/filemanager/browser/default/connectors/php/io.php: add at the start of CreateServerFolder() the line: - see http://sourceforge.net/tracker/index.php?func=detail&aid=1386086&group_id=75348&atid=543655 for official patch request
	#MLS# Ensure the folder path has no double-slashes, or mkdir may fail on certain platforms
	while (strpos ($folderPath, '//') !== false) {$folderPath = str_replace ('//', '/', $folderPath);}
	
	
	The following are experienced deficiencies in FCKeditor 2.2:
	- Auto-hyperlinking doesn't work in Firefox	http://sourceforge.net/tracker/index.php?func=detail&aid=1314815&group_id=75348&atid=543653
	- Minor problem: In a 'normal' paragraph, format box doesn't update to normal, but headings work fine
	- CSS underlining inheritance seems wrong in Firefox See: http://sourceforge.net/tracker/?group_id=75348&atid=543653&func=detail&aid=1230485 and https://bugzilla.mozilla.org/show_bug.cgi?id=300358
	- API deficiency: DocType = '' See: https://sourceforge.net/tracker/index.php?func=detail&aid=1386094&group_id=75348&atid=543653
	- API deficiency: FormatIndentator = "\t"
	- API deficiency: ToolbarSets all have to be set outside PHP
	
	*/
	
	function richtext ($suppliedArguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentDefaults = array (
			'name'					=> NULL,	# Name of the element
			'editable'				=> true,	# Whether the widget is editable (if not, a hidden element will be substituted but the value displayed)
			'title'					=> NULL,		# Introductory text
			'description'			=> '',		# Description text
			'output'				=> array (),# Presentation format
			'required'				=> false,	# Whether required or not
			'regexp'				=> '',		# Regular expression against which the submission must validate
			'disallow'				=> '',		# Regular expression against which the submission must not validate
			'width'					=> '100%',		# Width
			'height'				=> '400px',		# Height
			'default'				=> '',		# Default value (optional)
			'editorBasePath'		=> '/_fckeditor/',	# Location of the editor files
			'editorToolbarSet'		=> 'pureContent',	# Editor toolbar set
			'editorConfig'				=> array (	# Editor configuration
				'CustomConfigurationsPath' => '/_fckeditor/fckconfig-customised.js',
				'FontFormats'			=> 'p;h1;h2;h3;h4;h5;h6;pre',
				'UserFilesPath'			=> '/',
				'EditorAreaCSS'			=> '',
				'BaseHref'				=> '',	// Doesn't work, and http://sourceforge.net/tracker/?group_id=75348&atid=543653&func=detail&aid=1205638 doesn't fix it
				'GeckoUseSPAN'			=> false,	#!# Even in .js version this seems to have no effect
				'StartupFocus'			=> false,
				'ToolbarCanCollapse'	=> false,
				// 'DocType' => '',	// Prevent left-right scrollbars	// Has to go in the main config file (not customised file or PHP constructor)
				// 'FormatIndentator'		=> "\t",
				/* Doesn't (and theoretically shouldn't) work: */
				#!# Try to get Javascript Array Literal syntax to work (firstly in config.js then here as a string) - see http://www.devhood.com/tutorials/tutorial_details.aspx?tutorial_id=729
				#'ToolbarSets' => array ('pureContent' => array (	// Syntax as given at  http://www.heygrady.com/tutorials/example-2b2.php.txt
				#	array ('Source'),
				#	array ('Cut','Copy','Paste','PasteText','PasteWord','-'/*,'SpellCheck'*/),
				#	array ('Undo','Redo','-','Find','Replace','-','SelectAll','RemoveFormat'),
				#	array ('Bold','Italic','StrikeThrough','-','Subscript','Superscript'),
				#	array ('OrderedList','UnorderedList','-','Outdent','Indent'),
				#	array ('Link','Unlink','Anchor'),
				#	array ('Image','Table','Rule','SpecialChar'/*,'UniversalKey'*/),
				#	/* array ('Form','Checkbox','Radio','Input','Textarea','Select','Button','ImageButton','Hidden'),*/
				#	array (/*'FontStyleAdv','-','FontStyle','-',*/'FontFormat','-','-'),
				#	array ('About'),
				#)),
				#!# Consider finding a way of getting the new MCPUK browser working - the hard-coded paths in the default browser which have to be hacked is far from ideal
				'LinkBrowserURL'		=> '/_fckeditor/editor/filemanager/browser/default/browser.html?Connector=connectors/php/connector.php',
				'ImageBrowserURL'		=> '/_fckeditor/editor/filemanager/browser/default/browser.html?Type=Image&Connector=connectors/php/connector.php',
			),
			'protectEmailAddresses' => true,	// Whether to obfuscate e-mail addresses
			'externalLinksTarget'	=> '_blank',	// The window target name which will be instanted for external links (as made within the editing system) or false
			'directoryIndex' => 'index.html',		// Default directory index name
			'imageAlignmentByClass'	=> true,		// Replace align="foo" with class="foo" for images
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
		
		$elementValue = $widget->getValue ();
		
		# Assign the initial value if the form is not posted (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid), or clean it if posted
		$elementValue = (!$this->formPosted ? $arguments['default'] : $this->richtextClean ($this->form[$arguments['name']], $arguments));
		
		# Define the widget's core HTML
		if ($arguments['editable']) {
			
			# Define the widget's core HTML by instantiating the richtext editor module and setting required options
			require_once ('fckeditor.php');
			$editor = new FCKeditor ("{$this->settings['name']}[{$arguments['name']}]");
			$editor->BasePath	= $arguments['editorBasePath'];
			$editor->Width		= $arguments['width'];
			$editor->Height		= $arguments['height'];
			$editor->ToolbarSet	= $arguments['editorToolbarSet'];
			$editor->Value		= $elementValue;
			$editor->Config		= $arguments['editorConfig'];
			$widgetHtml = $editor->CreateHtml ();
		} else {
			$widgetHtml = $this->form[$arguments['name']];
			$widgetHtml .= '<input name="' . $this->settings['name'] . "[{$arguments['name']}]\" type=\"hidden\" value=\"" . htmlspecialchars ($this->form[$arguments['name']]) . '" />';
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
			'html' => $widgetHtml,
			'title' => $arguments['title'],
			'description' => $arguments['description'],
			'restriction' => (isSet ($restriction) && $arguments['editable'] ? $restriction : false),
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $arguments['required'],
			'requiredButEmpty' => $requiredButEmpty,
			'suitableAsEmailTarget' => false,
			'output' => $arguments['output'],
			'data' => (isSet ($data) ? $data : NULL),
			'datatype' => "`{$arguments['name']}` " . 'BLOB' . ($arguments['required'] ? ' NOT NULL' : '') . " COMMENT '" . (addslashes ($arguments['title'])) . "'",
		);
	}
	
	
	# Function to clean the content
	function richtextClean ($content, &$arguments)
	{
		# If the tidy extension is not available (e.g. PHP4), perform cleaning with the Tidy API
		if (function_exists ('tidy_parse_string')) {
			
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
			
			# Tidy up the output; see http://www.zend.com/php5/articles/php5-tidy.php for a tutorial
			$content = tidy_parse_string ($content, $parameters);
			tidy_clean_repair ($content);
			$content = tidy_get_output ($content);
		}
		
		# Start an array of regexp replacements
		$replacements = array ();
		
		# Protect e-mail spanning from later replacement in the main regexp block
		if ($arguments['protectEmailAddresses']) {
			$replacements += array (
				'<span>@</span>' => '<TEMPspan>@</TEMPspan>',
			);
		}
		
		# Define main regexp replacements
		$replacements += array (
			'<\?xml:namespace([^>]*)>' => '',	// Remove Word XML namespace tags
			'<o:p> </o:p>'	=> '',	// WordHTML characters
			'<span>([^<]*)</span>' => '<TEMP2span>\\1</TEMP2span>',	// Protect FIR-style spans
			"</?span([^>]*)>"	=> '',	// Remove other spans
			'[[:space:]]*<h([1-6]{1})([^>]*)>[[:space:]]</h([1-6]{1})>[[:space:]]*' => '',	// Headings containing only whitespace
			'<h([2-6]+)'	=> "\n<h\\1",	// Line breaks before headings 2-6
			'<br /></h([1-6]+)>'	=> "</h\\1>",	// Pointless line breaks just before a heading closing tag
			'</h([1-6]+)>'	=> "</h\\1>\n",	// Line breaks after all headings
			"<(li|tr|/tr|tbody|/tbody)"	=> "\t<\\1",	// Indent level-two tags
			"<td"	=> "\t\t<td",	// Double-indent level-three tags
			" href=\"{$arguments['editorBasePath']}editor/"	=> ' href=\"',	// Workaround for Editor basepath bug
			' href="([^"]*)/' . $arguments['directoryIndex'] . '"'	=> ' href="\1/"',	// Chop off directory index links
		);
		
		# Obfuscate e-mail addresses
		if ($arguments['protectEmailAddresses']) {
			$replacements += array (
				'<TEMPspan>@</TEMPspan>' => '<span>&#64;</span>',
				'<TEMP2span>([^<]*)</TEMP2span>' => '<span>\\1</span>',	// Replace FIR-style spans back
				'<a href="([^@]*)@([^"]*)">' => '<a href="mailto:\1@\2">',	// Initially catch badly formed HTML versions that miss out mailto: (step 1)
				'<a href="mailto:mailto:' => '<a href="mailto:',	// Initially catch badly formed HTML versions that miss out mailto: (step 2)
				'<a href="mailto:([^@]*)@([^"]*)">([^@]*)@([^"]*)</a>' => '\3<span>&#64;</span>\4',
				'<a href="mailto:([^@]*)@([^"]*)">([^<]*)</a>' => '\3 [\2<span>&#64;</span>\3]',
				'<span>@</span>' => '<span>&#64;</span>',
				'<span><span>&#64;</span></span>' => '<span>&#64;</span>',
				'([_a-z0-9-]+)(\.[_a-z0-9-]+)*@([a-z0-9-]+)(\.[a-z0-9-]+)*(\.[a-z]{2,6})' => '\1\2<span>&#64;</span>\3\4\5', // Non-linked, standard text, addresses
			);
		}
		
		# Ensure links to pages outside the page are in a new window
		if ($arguments['externalLinksTarget']) {
			$replacements += array (
				'<a target="([^"]*)" href="([^"]*)"([^>]*)>' => '<a href="\2" target="\1"\3>',	// Move existing target to the end
				'<a href="(http:|https:)//([^"]*)"([^>]*)>' => '<a href="\1//\2" target="' . $arguments['externalLinksTarget'] . '"\3>',	// Add external links
				'<a href="([^"]*)" target="([^"]*)" target="([^"]*)"([^>]*)>' => '<a href="\1" target="\2"\4>',	// Remove any duplication
			);
		}
		
		# Replacement of image alignment with a similarly-named class
		if ($arguments['imageAlignmentByClass']) {
			$replacements += array (
				'<img([^>]*) align="(left|center|centre|right)"([^>]*)>' => '<img\1 class="\2"\3>',
			);
		}
		
		# Perform the replacements
		foreach ($replacements as $find => $replace) {
			$content = eregi_replace ($find, $replace, $content);
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
			'output'				=> array (),# Presentation format
			'multiple'				=> false,	# Whether to create a multiple-mode select box
			'required'		=> 0,		# The minimum number which must be selected (defaults to 0)
			'size'			=> 5,		# Number of rows visible in multiple mode (optional; defaults to 1)
			'default'				=> array (),# Pre-selected item(s)
			'forceAssociative'		=> false,	# Force the supplied array of values to be associative
			'nullText'				=> $this->settings['nullText'],	# Override null text for a specific select widget
		);
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, __FUNCTION__);
		
		$arguments = $widget->getArguments ();
		
		# Ensure the initial value(s) is an array, even if only an empty one, converting if necessary
		$arguments['default'] = application::ensureArray ($arguments['default']);
		
		# If the widget is not editable, fix the form value to the default
		if (!$arguments['editable']) {$this->form[$arguments['name']] = $arguments['default'];}
		
		# Obtain the value of the form submission (which may be empty)
		$widget->setValue (isSet ($this->form[$arguments['name']]) ? $this->form[$arguments['name']] : array ());
		
		$elementValue = $widget->getValue ();
		
		# If the values are not an associative array, convert the array to value=>value format and replace the initial array
		$arguments['values'] = $this->ensureHierarchyAssociative ($arguments['values'], $arguments['forceAssociative'], $arguments['name']);
		
		# If a multidimensional array, cache the multidimensional version, and flatten the main array values
		if (application::isMultidimensionalArray ($arguments['values'])) {
			$arguments['_valuesMultidimensional'] = $arguments['values'];
			$arguments['values'] = application::flattenMultidimensionalArray ($arguments['values']);
		}
		
		# Check that the array of values is not empty
		#!# Only run other checks below if this error isn't thrown
		if (empty ($arguments['values'])) {$this->formSetupErrors['selectNoValues'] = 'No values have been set as selection items.';}
		
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
		
		# Perform substitution of the special syntax to set the value of a URL-supplied GET value as the initial value; if the supplied item is not present, ignore it; otherwise replace the default(s) array with the single selected item; this can only be applied once
		#!# Apply this to checkboxes and radio buttons also
		#!# Need to make 'url:$' in the values array not allowable as a genuine option]
		$identifier = 'url:$';
		foreach ($arguments['default'] as $key => $defaultValue) {
			if (substr ($defaultValue, 0, strlen ($identifier)) == $identifier) {
				$urlArgumentKey = substr ($defaultValue, strlen ($identifier));
				# Ensure that the URL supplied is one of the possible values, or delete the identifier key entirely
				if (!$arguments['default'][$key] = application::urlSuppliedValue ($urlArgumentKey, array_keys ($arguments['values']))) {
					unset ($arguments['default'][$key]);
				}
			}
		}
		
		# Ensure that all initial values are in the array of values
		$this->ensureDefaultsAvailable ($arguments);
		
		# Emulate the need for the field to be 'required', i.e. the minimum number of fields is greater than 0
		$required = ($arguments['required'] > 0);
		
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
			$restriction = (($arguments['required'] > 1) ? "Minimum {$arguments['required']} required; use Control/Shift" : 'Use Control/Shift for multiple');
		}
		
		# Define the widget's core HTML
		if ($arguments['editable']) {
			
			# Add a null field to the selection if in multiple mode and a value is required (for single fields, null is helpful; for multiple not required, some users may not know how to de-select a field)
			#!# Creates error if formSetupErrors['selectNoValues'] thrown - shouldn't be getting this far
			if (!$arguments['multiple'] || !$arguments['required']) {
				$arguments['valuesWithNull'] = array ('' => $arguments['nullText']) + $arguments['values'];
				if (isSet ($arguments['_valuesMultidimensional'])) {
					$arguments['_valuesMultidimensionalWithNull'] = array ('' => $arguments['nullText']) + $arguments['_valuesMultidimensional'];
				}
			}
			
			# Create the widget; this has to split between a non- and a multi-dimensional array because converting all to the latter makes it indistinguishable from a single optgroup array
			$widgetHtml = "\n\t\t\t<select name=\"" . $this->settings['name'] . "[{$arguments['name']}][]\"" . (($arguments['multiple']) ? " multiple=\"multiple\" size=\"{$arguments['size']}\"" : '') . '>';
			if (!isSet ($arguments['_valuesMultidimensional'])) {
				foreach ($arguments['valuesWithNull'] as $value => $visible) {
					$widgetHtml .= "\n\t\t\t\t" . '<option value="' . htmlspecialchars ($value) . '"' . (in_array ($value, $elementValue) ? ' selected="selected"' : '') . '>' . htmlspecialchars ($visible) . '</option>';
				}
			} else {
				
				# Multidimensional version, which adds optgroup labels
				foreach ($arguments['_valuesMultidimensionalWithNull'] as $key => $mainValue) {
					if (is_array ($mainValue)) {
						$widgetHtml .= "\n\t\t\t\t\t<optgroup label=\"$key\">";
						foreach ($mainValue as $value => $visible) {
							$widgetHtml .= "\n\t\t\t\t\t\t" . '<option value="' . htmlspecialchars ($value) . '"' . (in_array ($value, $elementValue) ? ' selected="selected"' : '') . '>' . htmlspecialchars ($visible) . '</option>';
						}
						$widgetHtml .= "\n\t\t\t\t\t</optgroup>";
					} else {
						$widgetHtml .= "\n\t\t\t\t" . '<option value="' . htmlspecialchars ($key) . '"' . (in_array ($key, $elementValue) ? ' selected="selected"' : '') . '>' . htmlspecialchars ($mainValue) . '</option>';
					}
				}
			}
			$widgetHtml .= "\n\t\t\t</select>\n\t\t";
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
					$widgetHtml .= "\n\t\t\t" . '<input name="' . $this->settings['name'] . "[{$arguments['name']}][]\" type=\"hidden\" value=\"" . htmlspecialchars ($value) . '" />';
				}
			}
			
			# Re-assign the values back to the 'submitted' form value
			$elementValue = array_keys ($presentableDefaults);
		}
		
		# Re-assign back the value
		$this->form[$arguments['name']] = $elementValue;
		
		# Get the posted data; there is no need to clear null submissions because it will just get ignored, not being in the values list
		if ($this->formPosted) {
			
			# For the component array, loop through each defined element name and assign the boolean value for it
			foreach ($arguments['values'] as $value => $visible) {
				#!# $submittableValues is defined above and is similar: refactor to remove these lines
				$data['rawcomponents'][$value] = (in_array ($value, $this->form[$arguments['name']]));
			}
			
			# For the compiled version, separate the compiled items by a comma-space
			$data['compiled'] = implode (",\n", $this->form[$arguments['name']]);
			
			# For the presented version, substitute the visible text version used for the actual value if necessary
			$chosen = array ();
			foreach ($this->form[$arguments['name']] as /*$key =>*/ $value) {
				if (isSet ($arguments['values'][$value])) {
					$chosen[] = $arguments['values'][$value];
				}
			}
			$data['presented'] = implode (",\n", $chosen);
		}
		
		# Compile the datatype
		foreach ($arguments['values'] as $key => $value) {
			$datatype[] = str_replace ("'", "\'", $key);
		}
		
		# Add the widget to the master array for eventual processing
		$this->elements[$arguments['name']] = array (
			'type' => __FUNCTION__,
			'html' => $widgetHtml,
			'title' => $arguments['title'],
			'description' => $arguments['description'],
			'restriction' => (isSet ($restriction) && $arguments['editable'] ? $restriction : false),
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $required,
			'requiredButEmpty' => $requiredButEmpty,
			'suitableAsEmailTarget' => $this->_suitableAsEmailTarget (array_keys ($arguments['values']), $arguments),
			'output' => $arguments['output'],
			'data' => (isSet ($data) ? $data : NULL),
			'values' => $arguments['values'],
			'multiple' => $arguments['multiple'],
			'datatype' => "`{$arguments['name']}` " . "ENUM ('" . implode ("', '", $datatype) . "')" . ($arguments['required'] ? ' NOT NULL' : '') . " COMMENT '" . (addslashes ($arguments['title'])) . "'",
		);
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
			'output'				=> array (),# Presentation format
			'required'				=> false,	# Whether required or not
			'default'				=> array (),# Pre-selected item
			'linebreaks'			=> true,	# Whether to put line-breaks after each widget: true = yes (default) / false = none / array (1,2,5) = line breaks after the 1st, 2nd, 5th items
			'forceAssociative'		=> false,	# Force the supplied array of values to be associative
			'nullText'				=> $this->settings['nullText'],	# Override null text for a specific select widget (if false, the master value is assumed)
		);
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, __FUNCTION__);
		
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
		
		# Check whether the field satisfies any requirement for a field to be required
		$requiredButEmpty = $widget->requiredButEmpty ();
		
		$elementValue = $widget->getValue ();
		
		# If the values are not an associative array, convert the array to value=>value format and replace the initial array
		$arguments['values'] = $this->ensureHierarchyAssociative ($arguments['values'], $arguments['forceAssociative'], $arguments['name']);
		
		/* #!# Enable when implementing fieldset grouping
		# If a multidimensional array, cache the multidimensional version, and flatten the main array values
		if (application::isMultidimensionalArray ($arguments['values'])) {
			$arguments['_valuesMultidimensional'] = $arguments['values'];
			$arguments['values'] = application::flattenMultidimensionalArray ($arguments['values']);
		}
		*/
		
		# Check that the array of values is not empty
		if (empty ($arguments['values'])) {$this->formSetupErrors['radiobuttonsNoValues'] = 'No values have been set for the set of radio buttons.';}
		
		# Ensure that there cannot be multiple initial values set if the multiple flag is off; note that the default can be specified as an array, for easy swapping with a select (which in singular mode behaves similarly)
		$arguments['default'] = application::ensureArray ($arguments['default']);
		if (count ($arguments['default']) > 1) {
			$this->formSetupErrors['defaultTooMany'] = "In the <strong>{$arguments['name']}</strong> element, $totalDefaults total initial values were assigned but only one can be set as a default.";
		}
		
		# Ensure that all initial values are in the array of values
		$this->ensureDefaultsAvailable ($arguments);
		
		# If the field is not a required field (and therefore there is a null text field), ensure that none of the values have an empty string as the value (which is reserved for the null)
		#!# Policy question: should empty values be allowed at all? If so, make a special constant for a null field but which doesn't have the software name included
		if (!$arguments['required'] && in_array ('', array_keys ($arguments['values']))) {
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
				$elementId = $this->cleanId ("{$arguments['name']}_{$value}");
				
				#!# Dagger hacked in - fix properly for other such characters; consider a flag somewhere to allow entities and HTML tags to be incorporated into the text (but then cleaned afterwards when printed/e-mailed)
				#$visible = str_replace ('†', '&dagger;', htmlspecialchars ($visible));
				$widgetHtml .= "\n\t\t\t" . '<input type="radio" name="' . $this->settings['name'] . "[{$arguments['name']}]\"" . ' value="' . htmlspecialchars ($value) . '"' . ($value == $elementValue ? ' checked="checked"' : '') . ' id="' . $elementId . '"' . " /><label for=\"" . $elementId . '">' . htmlspecialchars ($visible) . '</label>';
				
				# Add a line break if required
				if (($arguments['linebreaks'] === true) || (is_array ($arguments['linebreaks']) && in_array ($subwidgetIndex, $arguments['linebreaks']))) {$widgetHtml .= '<br />';}
				$subwidgetIndex++;
			}
			$widgetHtml .= "\n\t\t";
		} else {
			
			# Set the widget HTML if any default is given
			if ($arguments['default']) {
				$widgetHtml  = htmlspecialchars ($arguments['values'][$elementValue]);
				$widgetHtml .= "\n\t\t\t" . '<input name="' . $this->settings['name'] . "[{$arguments['name']}][]\" type=\"hidden\" value=\"" . htmlspecialchars ($elementValue) . '" />';
			}
		}
		
		# Re-assign back the value
		$this->form[$arguments['name']] = $elementValue;
		
		# Get the posted data; there is no need to clear null submissions because it will just get ignored, not being in the values list
		if ($this->formPosted) {
			
			# For the rawcomponents version, create an array with every defined element being assigned as itemName => boolean
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
			'html' => $widgetHtml,
			'title' => $arguments['title'],
			'description' => $arguments['description'],
			'restriction' => (isSet ($restriction) && $arguments['editable'] ? $restriction : false),
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $arguments['required'],
			'requiredButEmpty' => $requiredButEmpty,
			'suitableAsEmailTarget' => $arguments['required'],
			'output' => $arguments['output'],
			'data' => (isSet ($data) ? $data : NULL),
			'values' => $arguments['values'],
			'datatype' => "`{$arguments['name']}` " . "ENUM ('" . implode ("', '", $datatype) . "')" . ($arguments['required'] ? ' NOT NULL' : '') . " COMMENT '" . (addslashes ($arguments['title'])) . "'",
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
			'values'				=> array (),# Simple array of selectable values
			'title'					=> '',		# Introductory text
			'description'			=> '',		# Description text
			'output'				=> array (),# Presentation format
			'required'		=> 0,		# The minimum number which must be selected (defaults to 0)
			'maximum'		=> 0,		# The maximum number which must be selected (defaults to 0, i.e. no maximum checking done)
			'default'			=> array (),# Pre-selected item(s)
			'forceAssociative'		=> false,	# Force the supplied array of values to be associative
			'linebreaks'			=> true,	# Whether to put line-breaks after each widget: true = yes (default) / false = none / array (1,2,5) = line breaks after the 1st, 2nd, 5th items
		);
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, __FUNCTION__);
		
		$arguments = $widget->getArguments ();
		
		# Ensure the initial value(s) is an array, even if only an empty one, converting if necessary
		$arguments['default'] = application::ensureArray ($arguments['default']);
		
		# Obtain the value of the form submission (which may be empty)
		$widget->setValue (isSet ($this->form[$arguments['name']]) ? $this->form[$arguments['name']] : array ());
		
		$elementValue = $widget->getValue ();
		
		# If the values are not an associative array, convert the array to value=>value format and replace the initial array
		$arguments['values'] = $this->ensureHierarchyAssociative ($arguments['values'], $arguments['forceAssociative'], $arguments['name']);
		
		/* #!# Enable when implementing fieldset grouping
		# If a multidimensional array, cache the multidimensional version, and flatten the main array values
		if (application::isMultidimensionalArray ($arguments['values'])) {
			$arguments['_valuesMultidimensional'] = $arguments['values'];
			$arguments['values'] = application::flattenMultidimensionalArray ($arguments['values']);
		}
		*/
		
		# Check that the array of values is not empty
		if (empty ($arguments['values'])) {$this->formSetupErrors['checkboxesNoValues'] = 'No values have been set for the set of checkboxes.';}
		
		# Check that the given minimum required is not more than the number of checkboxes actually available
		$totalSubItems = count ($arguments['values']);
		if ($arguments['required'] > $totalSubItems) {$this->formSetupErrors['checkboxesMinimumMismatch'] = "In the <strong>{$arguments['name']}</strong> element, The required minimum number of checkboxes (<strong>{$arguments['required']}</strong>) specified is above the number of checkboxes actually available (<strong>$totalSubItems</strong>).";}
		if ($arguments['maximum'] && $arguments['required'] && ($arguments['maximum'] < $arguments['required'])) {$this->formSetupErrors['checkboxesMaximumMismatch'] = "In the <strong>{$arguments['name']}</strong> element, A maximum and a minimum number of checkboxes have both been specified but this maximum (<strong>{$arguments['maximum']}</strong>) is less than the minimum (<strong>{$arguments['required']}</strong>) required.";}
		
		# Ensure that all initial values are in the array of values
		$this->ensureDefaultsAvailable ($arguments);
		
		# Start a tally to check the number of checkboxes checked
		$checkedTally = 0;
		
		# Loop through each element subname and construct HTML
		$widgetHtml = '';
		if ($arguments['editable']) {
			/* #!# Write branching code around here which uses _valuesMultidimensional, when implementing fieldset grouping */
			$subwidgetIndex = 1;
			foreach ($arguments['values'] as $value => $visible) {
				
				# Define the element ID, which must be unique	
				$elementId = $this->cleanId ("{$this->settings['name']}__{$arguments['name']}__{$value}");
				
				# Assign the initial value if the form is not posted (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid)
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
				
				# Create the HTML; note that spaces (used to enable the 'label' attribute for accessibility reasons) in the ID will be replaced by an underscore (in order to remain valid XHTML)
				$widgetHtml .= "\n\t\t\t" . '<input type="checkbox" name="' . $this->settings['name'] . "[{$arguments['name']}][{$value}]" . '" id="' . $elementId . '" value="true"' . $stickynessHtml . ' /><label for="' . $elementId . '">' . htmlspecialchars ($visible) . '</label>';
				
				# Add a line break if required
				if (($arguments['linebreaks'] === true) || (is_array ($arguments['linebreaks']) && in_array ($subwidgetIndex, $arguments['linebreaks']))) {$widgetHtml .= '<br />';}
				$subwidgetIndex++;
			}
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
					$widgetHtml .= "\n\t\t\t" . '<input name="' . $this->settings['name'] . "[{$arguments['name']}][{$value}]\" type=\"hidden\" value=\"true\" />";
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
		
		# Get the posted data
		if ($this->formPosted) {
			
			# For the component array, create an array with every defined element being assigned as itemName => boolean; checking is done against the available values rather than the posted values to prevent offsets
			foreach ($arguments['values'] as $value => $visible) {
				$data['rawcomponents'][$value] = (isSet ($this->form[$arguments['name']][$value]) && $this->form[$arguments['name']][$value] == 'true');
			}
			
			# Make an array of those items checked, starting with an empty array in case none are checked
			$checked = array ();
			$checkedPresented = array ();
			foreach ($data['rawcomponents'] as $key => $value) {
				if ($value) {
					$checked[] = $key;
					
					# For the presented version, substitute the index name with the presented name
					$checkedPresented[] = $arguments['values'][$key];
				}
			}
			
			# Separate the compiled/presented items by a comma-newline
			$data['compiled'] = implode (",\n", $checked);
			$data['presented'] = implode (",\n", $checkedPresented);
		}
		
		# Compile the datatype
		foreach ($arguments['values'] as $key => $value) {
			#!# NOT NULL handling needs to be inserted
			$checkboxDatatypes[] = "`" . /* $arguments['name'] . '-' . */ str_replace ("'", "\'", $key) . "` " . "ENUM ('true', 'false')" . " COMMENT '" . (addslashes ($arguments['title'])) . "'";
		}
		$datatype = implode (",\n", $checkboxDatatypes);
		
		# Add the widget to the master array for eventual processing
		$this->elements[$arguments['name']] = array (
			'type' => __FUNCTION__,
			'html' => $widgetHtml,
			'title' => $arguments['title'],
			'description' => $arguments['description'],
			'restriction' => (isSet ($restriction) && $arguments['editable'] ? $restriction : false),
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => false,
			'requiredButEmpty' => false, # This is covered by $elementProblems
			#!# Apply $this->_suitableAsEmailTarget () to checkboxes possibly
			'suitableAsEmailTarget' => false,
			'output' => $arguments['output'],
			'data' => (isSet ($data) ? $data : NULL),
			'values' => $arguments['values'],
			#!# Not correct - needs multisplit into boolean
			'datatype' => $datatype,
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
			'output'				=> array (),# Presentation format
			'required'				=> false,	# Whether required or not
			'level'					=> 'date',	# Whether to show a 'datetime' or just 'date' widget set
			'default'				=> '',		# Initial value - either 'timestamp' or an SQL string
		);
		
		# Load the date processing library
		#!# Ideally this really should be higher up in the class, e.g. in the setup area
		require_once ('datetime.php');
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, __FUNCTION__);
		
		$arguments = $widget->getArguments ();
		
		# Convert the default if using the 'timestamp' keyword; cache a copy for later use
		$isTimestamp = ($arguments['default'] == 'timestamp');
		if ($isTimestamp) {$arguments['default'] = date ('Y-m-d' . (($arguments['level'] == 'datetime') ? ' H:i:s' : ''));}
		
		# If the widget is not editable, fix the form value to the default
		if (!$arguments['editable']) {$this->form[$arguments['name']] = datetime::getDateTimeArray ($arguments['default']);}
		
		# Obtain the value of the form submission (which may be empty)  (ensure that a full date and time array exists to prevent undefined offsets in case an incomplete set has been posted)
		$widget->setValue (isSet ($this->form[$arguments['name']]) ? $this->form[$arguments['name']] : array ('year' => '', 'month' => '', 'day' => '', 'time' => ''));
		
		$elementValue = $widget->getValue ();
		
		# Start a flag later used for checking whether all fields are empty against the requirement that a field should be completed
		$requiredButEmpty = false;
		
		# Assign the initial value if the form is not posted (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid)
		if (!$this->formPosted) {
			$elementValue = datetime::getDateTimeArray ($arguments['default']);
		} else {
 			
			# Check whether all fields are empty, starting with assuming all fields are not incomplete
			$allFieldsIncomplete = false;
			if ($arguments['level'] == 'datetime') {
				if ((empty ($elementValue['day'])) && (empty ($elementValue['month'])) && (empty ($elementValue['year'])) && (empty ($elementValue['time']))) {$allFieldsIncomplete = true;}
			} else {
				if ((empty ($elementValue['day'])) && (empty ($elementValue['month'])) && (empty ($elementValue['year']))) {$allFieldsIncomplete = true;}
			}
			
			# If all fields are empty, and the widget is required, set that the field is required but empty
			if ($allFieldsIncomplete) {
				if ($arguments['required']) {$requiredButEmpty = true;}
			} else {
				
				# Deal with month conversion by adding leading zeros as required
				if (($elementValue['month'] > 0) && ($elementValue['month'] <= 12)) {$elementValue['month'] = sprintf ('%02s', $elementValue['month']);}
				
				# If automatic conversion is set and the year is two characters long, convert the date to four years by adding 19 or 20 as appropriate
				if (($this->settings['autoCenturyConversionEnabled']) && (strlen ($elementValue['year']) == 2)) {
					$elementValue['year'] = (($elementValue['year'] <= $this->settings['autoCenturyConversionLastYear']) ? '20' : '19') . $elementValue['year'];
				}
				
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
							if ($this->settings['autoCenturyConversionEnabled']) {
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
				
				# If the time is required in addition to the date, parse the time field, allowing flexible input syntax
				if ($arguments['level'] == 'datetime') {
					
					# Only do time processing if the time field isn't empty
					if (!empty ($elementValue['time'])) {
						
						# If the time parsing passes, substitute the submitted version with the parsed and corrected version
						if ($time = datetime::parseTime ($elementValue['time'])) {
							$elementValue['time'] = $time;
						} else {
							
							# If, instead, the time parsing fails, leave the original submitted version and add the problem to the errors array
							$elementProblems['timePartInvalid'] = 'The time part is invalid!';
						}
					}
				}
			}
		}
		
		# Describe restrictions on the widget
		if ($arguments['level'] == 'datetime') {$restriction = 'Time can be entered flexibly';}
		
		# Start to define the widget's core HTML
		if ($arguments['editable']) {
			#!# Add fieldsets to remaining form widgets or scrap
			$widgetHtml = "\n\t\t\t<fieldset>";
			
			# Add in the time if required
			if ($arguments['level'] == 'datetime') {
				$widgetHtml .= "\n\t\t\t\t" . '<span class="' . (!isSet ($elementProblems['timePartInvalid']) ? 'comment' : 'warning') . '">t:&nbsp;</span><input name="' . $this->settings['name'] . '[' . $arguments['name'] . '][time]" type="text" size="10" value="' . $elementValue['time'] . '" />';
			}
			
			# Define the date, month and year input boxes; if the day or year are 0 then nothing will be displayed
			$widgetHtml .= "\n\t\t\t\t" . '<span class="comment">d:&nbsp;</span><input name="' . $this->settings['name'] . '[' . $arguments['name'] . '][day]"  size="2" maxlength="2" value="' . (($elementValue['day'] != '00') ? $elementValue['day'] : '') . '" />&nbsp;';
			$widgetHtml .= "\n\t\t\t\t" . '<span class="comment">m:</span>';
			$widgetHtml .= "\n\t\t\t\t" . '<select name="' . $this->settings['name'] . '[' . $arguments['name'] . '][month]">';
			$months = array (1 => 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'June', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
			$widgetHtml .= "\n\t\t\t\t\t" . '<option value="">Select</option>';
			foreach ($months as $monthNumber => $monthName) {
				$widgetHtml .= "\n\t\t\t\t\t" . '<option value="' . sprintf ('%02s', $monthNumber) . '"' . (($elementValue['month'] == sprintf ('%02s', $monthNumber)) ? ' selected="selected"' : '') . '>' . $monthName . '</option>';
			}
			$widgetHtml .= "\n\t\t\t\t" . '</select>';
			$widgetHtml .= "\n\t\t\t\t" . '<span class="comment">y:&nbsp;</span><input size="4" name="' . $this->settings['name'] . '[' . $arguments['name'] . '][year]" maxlength="4" value="' . (($elementValue['year'] != '0000') ? $elementValue['year'] : '') . '" />' . "\n\t\t";
			$widgetHtml .= "\n\t\t\t</fieldset>";
		} else {
			
			# Non-editable version
			$widgetHtml  = datetime::presentDateFromArray ($elementValue, $arguments['level']) . ($isTimestamp ? '<br /><span class="comment">(Current date' . (($arguments['level'] == 'datetime') ? ' and time' : '') . ')</span>' : '');
			$widgetHtml .= "\n\t\t\t" . '<input name="' . $this->settings['name'] . "[{$arguments['name']}]\" type=\"hidden\" value=\"" . htmlspecialchars ($arguments['default']) . '" />';
		}
		
		# Re-assign back the value
		$this->form[$arguments['name']] = $elementValue;
		
		# Get the posted data
		if ($this->formPosted) {
			
			# Map the components directly and assemble the elements into a string
			$data['rawcomponents'] = $this->form[$arguments['name']];
			
			# Ensure there is a presented and a compiled version
			$data['presented'] = '';
			$data['compiled'] = '';
			
			# If all items are not empty then produce compiled and presented versions
			#!# This needs to be ALWAYS assigned in case $data['compiled'] and $data['presented'] are referred to later
			if (!application::allArrayElementsEmpty ($this->form[$arguments['name']])) {
				
				# Make the compiled version be in SQL DATETIME format, i.e. YYYY-MM-DD HH:MM:SS
				$data['compiled'] = $this->form[$arguments['name']]['year'] . '-' . $this->form[$arguments['name']]['month'] . '-' . $this->form[$arguments['name']]['day'] . (($arguments['level'] == 'datetime') ? ' ' . $this->form[$arguments['name']]['time'] : '');
				
				# Make the presented version in english text
				#!# date () corrupts dates after 2038; see php.net/date. Suggest not re-presenting it if year is too great.
				$data['presented'] = datetime::presentDateFromArray ($this->form[$arguments['name']], $arguments['level']);
			}
		}
		
		# Add the widget to the master array for eventual processing
		$this->elements[$arguments['name']] = array (
			'type' => __FUNCTION__,
			'html' => $widgetHtml,
			'title' => $arguments['title'],
			'description' => $arguments['description'],
			'restriction' => (isSet ($restriction) && $arguments['editable'] ? $restriction : false),
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $arguments['required'],
			'requiredButEmpty' => $requiredButEmpty,
			'suitableAsEmailTarget' => false,
			'output' => $arguments['output'],
			'data' => (isSet ($data) ? $data : NULL),
			'datatype' => "`{$arguments['name']}` " . strtoupper ($arguments['level']) . ($arguments['required'] ? ' NOT NULL' : '') . " COMMENT '" . (addslashes ($arguments['title'])) . "'",
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
			'output'				=> array (),# Presentation format
			'directory'				=> NULL,	# Path to the file; any format acceptable
			'subfields'				=> 1,		# The number of widgets within the widget set (i.e. available file slots)
			'required'				=> 0,		# The minimum number which must be selected (defaults to 0)
			'size'					=> 30,		# Visible size (optional; defaults to 30)
			'disallowedExtensions'	=> array (),# Simple array of disallowed file extensions (Single-item string also acceptable)
			'allowedExtensions'		=> array (),# Simple array of allowed file extensions (Single-item string also acceptable; '*' means extension required)
			'enableVersionControl'	=> true,	# Whether uploading a file of the same name should result in the earlier file being renamed
			'forcedFileName'		=> false,	# Force to a specific filename
		);
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, __FUNCTION__);
		
		$arguments = $widget->getArguments ();
		
		# Obtain the value of the form submission (which may be empty)
		#!# NB The equivalent of this line was not present before refactoring
		$widget->setValue (isSet ($this->form[$arguments['name']]) ? $this->form[$arguments['name']] : array ());
		
		$elementValue = $widget->getValue ();
		
		# Cache the upload properties
		$this->uploadProperties[$arguments['name']] = $arguments;
		
		# Ensure the initial value(s) is an array, even if only an empty one, converting if necessary
		$arguments['disallowedExtensions'] = application::ensureArray ($arguments['disallowedExtensions']);
		$arguments['allowedExtensions'] = application::ensureArray ($arguments['allowedExtensions']);
		
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
		
		# Check that the minimum required is a whole number and that it is not greater than the number actually available
		if ($arguments['required'] != round ($arguments['required'])) {$this->formSetupErrors['uploadSubfieldsMinimumIncorrect'] = "You specified a non-whole number (<strong>{$arguments['required']}</strong>) for the number of file upload widgets in the <strong>{$arguments['name']}</strong> upload element which must the user must upload.";}
		if ($arguments['required'] > $arguments['subfields']) {$this->formSetupErrors['uploadSubfieldsMinimumMismatch'] = "The required minimum number of files which the user must upload (<strong>{$arguments['required']}</strong>) specified in the <strong>{$arguments['name']}</strong> upload element is above the number of files actually available to be specified for upload (<strong>{$arguments['subfields']}</strong>).";}
		
		# Check that the selected directory exists and is writable (or create it)
		if (!is_dir ($arguments['directory'])) {
			if (!application::directoryIsWritable ($arguments['directory'])) {
				$this->formSetupErrors['directoryNotWritable'] = "The directory specified for the <strong>{$arguments['name']}</strong> upload element is not writable. Please check that the file permissions to ensure that the webserver 'user' can write to the directory.";
			} else {
				#!# Third parameter doesn't exist in PHP4 - will this cause a crash?
				#!# umask seems to have no effect - needs testing
				mkdir ($arguments['directory'], 0755, $recursive = true);
			}
		}
		
		# Prevent more files being uploaded than the number of form elements (this is not strictly necessary, however, as the subfield looping below prevents the excess being processed)
		if (count ($elementValue) > $arguments['subfields']) {
			$elementProblems['subfieldsMismatch'] = 'You appear to have submitted more files than there are fields available.';
		}
		
		# Start a counter for the number of files apparently uploaded
		$apparentlyUploadedFiles = 0;
		
		# Start the HTML
		$widgetHtml = '';
		if ($arguments['subfields'] > 1) {$widgetHtml .= "\n\t\t\t";}
		
		# Loop through the number of fields required to create the widget and perform checks
		for ($subfield = 0; $subfield < $arguments['subfields']; $subfield++) {
			
			# Continue further processing if the file has been uploaded
			if (isSet ($elementValue[$subfield])) {
				
				# Increment the number of apparently uploaded files (irrespective of whether they pass other checks)
				$apparentlyUploadedFiles++;
				
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
			
			# Define the widget's core HTML; note that MAX_FILE_SIZE as mentioned in the PHP manual is non-standard and seemingly not supported by any browsers, so is not supported here - doing so would also require MAX_FILE_SIZE as a disallowed form name
			$widgetHtml .= '<input name="' . $this->settings['name'] . "[{$arguments['name']}][{$subfield}]\" type=\"file\" size=\"{$arguments['size']}\" />";
			$widgetHtml .= (($subfield != ($arguments['subfields'] - 1)) ? "<br />\n\t\t\t" : (($arguments['subfields'] == 1) ? '' : "\n\t\t"));
		}
		
		# If fields which don't have a file extension have been found, throw a user error
		if (isSet ($extensionsMissing)) {
			$elementProblems['fileExtensionAbsent'] = (count ($extensionsMissing) > 1 ? 'The files ' : 'The file ') . implode (', ', $extensionsMissing) . (count ($extensionsMissing) > 1 ? ' have' : ' has') . ' no file extension, but file extensions are required for files selected in this section.';
		}
		
		# If fields which have an invalid extension have been found, throw a user error
		if (isSet ($filenameInvalidSubfields)) {
			$elementProblems['fileExtensionMismatch'] = (count ($filenameInvalidSubfields) > 1 ? 'All files ' : 'The file ') . implode (', ', $filenameInvalidSubfields) . (count ($filenameInvalidSubfields) > 1 ? ' do not' : ' does not') . ' comply with the specified file extension rules for this section.';
		}
		
		# If any files have been uploaded, the user will need to re-select them.
		if ($apparentlyUploadedFiles > 0) {
			$this->elementProblems['generic']['reselectUploads'] = "You will need to reselect the $apparentlyUploadedFiles files you selected for uploading, because of problems elsewhere in the form. (Re-selection is a security requirement of your web browser.)";
			/*
			#!# Need a way to flag it in red but not be listed in "You didn't enter a value for the following required fields"
			# The requiredButEmpty flag is triggered, even though the element may not be required
			$requiredButEmpty = true;
			*/
		}
		
		# Check if the field is required (i.e. the minimum number of fields is greater than 0) and, if so, run further checks
		if ($required = ($arguments['required'] > 0)) {
			
			# If none have been uploaded, class this as requiredButEmpty
			if ($apparentlyUploadedFiles == 0) {
				$requiredButEmpty = true;
				
			# If too few have been uploaded, produce a individualised warning message
			} else if ($apparentlyUploadedFiles < $arguments['required']) {
				$elementProblems['underMinimum'] = ($arguments['required'] != $arguments['subfields'] ? 'At least' : 'All') . " <strong>{$arguments['required']}</strong> " . ($arguments['required'] > 1 ? 'files' : 'file') . ' must be submitted; you will need to reselect the ' . ($apparentlyUploadedFiles == 1 ? 'file' : "{$apparentlyUploadedFiles} files") . ' that you did previously select, for security reasons.';
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
		if (isSet ($restrictions)) {$restrictions = implode (";\n", $restrictions);}
		
		# Re-assign back the value
		$this->form[$arguments['name']] = $elementValue;
		
		# Add the widget to the master array for eventual processing
		$this->elements[$arguments['name']] = array (
			'type' => __FUNCTION__,
			'html' => $widgetHtml,
			'title' => $arguments['title'],
			'description' => $arguments['description'],
			'restriction' => (isSet ($restrictions) ? $restrictions : false),
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $required,
			'requiredButEmpty' => (isSet ($requiredButEmpty) ? $requiredButEmpty : false),
			'suitableAsEmailTarget' => false,
			'output' => $arguments['output'],
			'data' => NULL,	// Because the uploading can only be processed later, this is set to NULL
			#!# Not finished
#			'datatype' => "`{$arguments['name']}` " . 'VARCHAR (255)' . ($arguments['required'] ? ' NOT NULL' : '') . " COMMENT '" . (addslashes ($arguments['title'])) . "'",
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
			$widgetHtml .= "\n\t" . '<input type="hidden" name="' . $this->settings['name'] . "[{$arguments['name']}][$key]" . '" value="' . $value . '" />';
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
			'description' => false,
			'restriction' => false,
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => true,
			'requiredButEmpty' => false,
			'suitableAsEmailTarget' => false,
			'output' => $arguments['output'],
			'data' => (isSet ($data) ? $data : NULL),
			#!# Not finished
			#!# 'datatype' => "`{$arguments['name']}` " . 'VARCHAR (255)' . ($arguments['required'] ? ' NOT NULL' : '') . " COMMENT '" . (addslashes ($arguments['title'])) . "'",
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
			'data' => (isSet ($data) ? $data : NULL),
		);
	}
	
	
	# Function to ensure that all initial values are in the array of values
	function ensureDefaultsAvailable ($arguments)
	{
		# Convert to an array (for this local function only) if not already
		if (!is_array ($arguments['default'])) {
			$arguments['default'] = application::ensureArray ($arguments['default']);
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
	
	
	# Function to clean an HTML id attribute
	function cleanId ($id)
	{
		# Define the replacements
		$replacements = array (' ', ',', '†', '!', '(', ')', '[', ']',);
		
		# Perform the replacements
		$id = str_replace ($replacements, '_', $id);
		
		# Return the cleaned ID
		return $id;
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
		# Loop through each upload widget set which has been submitted (even if empty); note that _FILES is arranged differently depending on whether you are using 'formname[elementname]' or just 'elementname' as the element name - see "HTML array feature" note at www.php.net/features.file-upload
		foreach ($_FILES[$this->settings['name']]['name'] as $name => $subElements) {
			
			# Loop through each upload widget set's subelements (e.g. 4 items if there are 4 input tags within the widget set)
			foreach ($subElements as $key => $value) {
				
				# Map the file information into the main form element array
				if (!empty ($value)) {
					$_POST[$this->settings['name']][$name][$key] = array (
						'name' => $_FILES[$this->settings['name']]['name'][$name][$key],
						'type' => $_FILES[$this->settings['name']]['type'][$name][$key],
						'tmp_name' => $_FILES[$this->settings['name']]['tmp_name'][$name][$key],
						#'error' => $_FILES[$this->settings['name']]['error'][$name][$key],
						'size' => $_FILES[$this->settings['name']]['size'][$name][$key],
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
		$this->dumpData ($this->elements);
		
		# Show submitted form elements, if the form has been submitted
		if ($this->formPosted) {
			echo "\n\n" . '<h3 id="submitted">Submitted form elements - $this->form :</h3>';
			$this->dumpData ($this->form);
		}
		
		# End the debugging HTML
		echo "\n\n" . '<a name="remainder"></a>';
		echo "\n</div>";
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
		
		# Assign the subject title
		$this->configureResultEmailedSubjectTitle['email'] = $subjectTitle;
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
	function setOutputConfirmationEmail ($chosenelementName, $administrator = '', $includeAbuseNotice = true, $subjectTitle = 'Form submission results', $displayUnsubmitted = true)
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
		# Open the surrounding <div> if relevant
		if ($this->settings['div']) {echo "\n\n<div class=\"{$this->settings['div']}\">";}
		
		# Show the presentation matrix if required (this is allowed to bypass the form setup so that the administrator can see what corrections are needed)
		if ($this->settings['displayPresentationMatrix']) {$this->displayPresentationMatrix ();}
		
		# Check if the form and PHP environment has been set up OK
		if (!$this->_setupOk ()) {return false;}
		
		# Show debugging information firstly if required
		if ($this->settings['debug']) {$this->showDebuggingInformation ();}
		
		# Check whether the user is a valid user (must be before the setupOk check)
		if (!$this->validUser ()) {return false;}
		
		# Check whether the facility is open
		if (!$this->facilityIsOpen ()) {return false;}
		
		# Validate hidden security fields
		if ($this->hiddenSecurityFieldSubmissionInvalid ()) {return false;}
		
		# If the form is not posted or contains problems, display it and flag that it has been displayed
		if (!$this->formPosted || $this->getElementProblems ()) {
			
			# Run the callback function if one is set
			if ($this->settings['callback']) {
				$this->settings['callback'] ($this->elementProblems ? -1 : 0);
			}
			
			# Display the form and any problems then end
			echo $this->constructFormHtml ($this->elements, $this->elementProblems);
			if ($this->settings['div']) {echo "\n</div>";}
			return false;
		}
		
		# Process any form uploads
		$this->doUploads ();
		
		# Prepare the data
		$this->outputData = $this->prepareData ();
		
		# If required, display a summary confirmation of the result
		if ($this->settings['formCompleteText']) {echo "\n" . '<p class="completion">' . $this->settings['formCompleteText'] . ' </p>';}
		
		# Loop through each of the processing methods and output it based on the requested method
		foreach ($this->outputMethods as $outputType => $required) {
			$this->outputData ($outputType);
		}
		
		# If required, display a link to reset the page
		if ($this->settings['formCompleteText']) {echo "\n" . '<p><a href="' . $_SERVER['REQUEST_URI'] . '">Click here to reset the page.</a></p>';}
		
		# Close the surrounding <div> if relevant
		if ($this->settings['div']) {echo "\n\n</div>";}
		
		# Return the data
		return $this->outputData ('processing');
	}
	
	
	## Form processing support ##
	
	# Function to determine whether this facility is open
	function facilityIsOpen ()
	{
		# Check that the opening time has passed, if one is specified, ensuring that the date is correctly specified
		if ($this->settings['opening']) {
			if (time () < strtotime ($this->settings['opening'] . ' GMT')) {
				echo '<p class="warning">This facility is not yet open. Please return later.</p>';
				return false;
			}
		}
		
		# Check that the closing time has passed
		if ($this->settings['closing']) {
			if (time () > strtotime ($this->settings['closing'] . ' GMT')) {
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
		if (!$this->settings['validUsers']) {return true;}
		
		# If '*' is specified for valid users, allow any through
		if ($this->settings['validUsers'][0] == '*') {return true;}
		
		# If the username is supplied in a list, return true
		if (in_array ($this->settings['user'], $this->settings['validUsers'])) {return true;}
		
		# Otherwise state that the user is not in the list and return false
		echo "\n" . '<p class="warning">You do not appear to be in the list of valid users. If you believe you should be, please contact the webmaster to resolve the situation.</p>';
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
		
		# Validate the output format syntax items, looping through each and adding it to an array of items if an mispelt/unsupported item is found
		#!# Move this into a new widget object's constructor
		$formatSyntaxInvalidElements = array ();
		foreach ($this->elements as $name => $elementAttributes) {
			if (!$this->outputFormatSyntaxValid ($elementAttributes['output'])) {
				$formatSyntaxInvalidElements[$name] = true;
			}
		}
		if (!empty ($formatSyntaxInvalidElements)) {$this->formSetupErrors['outputFormatMismatch'] = 'The following field ' . (count ($formatSyntaxInvalidElements) == 1 ? 'name has' : 'names have') . ' an incorrectly set up output format specification in the form setup: <strong>' . implode ('</strong>, <strong>', array_keys ($formatSyntaxInvalidElements)) .  '</strong>; the administrator should switch on the \'displayPresentationMatrix\' option in the settings to check the syntax.';}
		
		# Check that the output format for each item against each output type is valid
		#!# This could probably do with refactoring to a separate function once the functionality is moved into the new widget object's constructor
		$formatUnsupportedElements = array ();
		$availableOutputFormats = $this->presentationDefaults ($returnFullAvailabilityArray = true, $includeDescriptions = false);
		foreach ($this->elements as $name => $elementAttributes) {  // Loop through each administrators's setup
			$widgetType = $this->elements[$name]['type'];
			foreach ($elementAttributes['output'] as $outputFormat => $setting) {  // Loop through each of the output formats specified in the administrators's setup
				if (!in_array ($setting, $availableOutputFormats[$widgetType][$outputFormat])) {
					$formatUnsupportedElements[$name] = true;
				}
			}
		}
		if (!empty ($formatUnsupportedElements)) {$this->formSetupErrors['outputFormatUnsupported'] = 'The following field ' . (count ($formatSyntaxInvalidElements) == 1 ? 'name has' : 'names have') . ' been allocated an output specification which is unsupported for the required output format(s) in the form setup: <strong>' . implode ('</strong>, <strong>', array_keys ($formatUnsupportedElements)) .  '</strong>; switching on the \'displayPresentationMatrix\' option in the settings will display the available types.';}
		
		# Check templating in template mode
		$this->setupTemplating ();
		
		# Validate the callback mode setup
		if ($this->settings['callback'] && !function_exists ($this->settings['callback'])) {
			$this->formSetupErrors['callback'] = 'You specified a callback function but no such function exists.';
		}
		
		# If there are any form setup errors - a combination of those just defined and those assigned earlier in the form processing, show them
		if (!empty ($this->formSetupErrors)) {echo application::showUserErrors ($this->formSetupErrors, $parentTabLevel = 1, (count ($this->formSetupErrors) > 1 ? 'Various errors were' : 'An error was') . " found in the setup of the form. The website's administrator needs to correct the configuration before the form will work:");}
		
		# Set that the form has effectively been displayed
		$this->formDisplayed = true;
		
		# Return true (i.e. form set up OK) if the errors array is empty
		return (empty ($this->formSetupErrors));
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
		
		# Define special placemarker names and whether they are required
		$specials = array (
			'PROBLEMS' => true,				// Placemarker for the element problems box
			'SUBMIT' => true,				// Placemarker for the submit button
			'RESET' => $this->settings['resetButton'],	// Placemarker for the reset button - if there is one
			'REQUIRED' => false,			// Placemarker for the required fields indicator text
		);
		
		# Loop through each special, allocating its replacement shortcut and checking it exists if necessary
		foreach ($specials as $special => $required) {
			$this->displayTemplateElementReplacementsSpecials[$special] = str_replace ($placemarker, $special, $this->settings['displayTemplatePatternSpecial']);
			if ($required && (substr_count ($this->displayTemplateContents, $this->displayTemplateElementReplacementsSpecials[$special]) !== 1)) {
				$missingElements[] = $this->displayTemplateElementReplacementsSpecials[$special];
			}
		}
		
		# Construct an array of missing elements if there are any; labels are considered optional
		if ($missingElements) {
			$this->formSetupErrors['templateElementsNotFound'] = 'The following element ' . ((count ($missingElements) == 1) ? 'string was' : 'strings were') . ' not present once only in the template you specified: ' . implode (', ', $missingElements);
		}
	}
	
	
	/**
	 * Function to validate the output format syntax
	 * @access private
	 */
	function outputFormatSyntaxValid ($elementOutputSpecificationArray)
	{
		# Define the supported types and values
		$supportedValues = array ('presented', 'compiled', 'rawcomponents');
		
		# If the element output specification array includes some items, check that the types and values are within the list of supported types and values
		if (!empty ($elementOutputSpecificationArray)) {
			foreach ($elementOutputSpecificationArray as $type => $value) {
				if ((!in_array ($type, $this->supportedTypes)) || (!in_array ($value, $supportedValues))) {
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
		if (version_compare (PHP_VERSION, $this->minimumPhpVersion, '<')) {$this->formSetupErrors['environmentPhpVersion'] = 'The server must be running PHP version <strong>' . $this->minimumPhpVersion . '</strong> or higher.';}
		
		# Check that global user variables cannot be imported into the program
		if ((bool) ini_get ('register_globals')) {$this->formSetupErrors['environmentRegisterGlobals'] = 'The PHP configuration setting register_globals must be set to <strong>off</strong>.';}
		
		# Check that raw PHP errors are not set to display on the screen
		if (!$this->settings['developmentEnvironment']) {
			if ((bool) ini_get ('display_errors')) {$this->formSetupErrors['environmentDisplayErrors'] = 'The PHP configuration setting display_errors must be set to <strong>false</strong>.';}
		}
		
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
				if ((!preg_match ('/^(\d+)([bkm]*)$/i', ini_get ('upload_max_filesize'))) || (!preg_match ('/^(\d+)([bkm]*)$/i', ini_get ('post_max_size')))) {
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
			if (ereg ('^_heading', $name)) {
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
		$html .= "\n" . '<form method="post" name="' . $this->settings['name'] . '" action="' . $this->settings['submitTo'] . '" enctype="' . ($this->uploadProperties ? 'multipart/form-data' : 'application/x-www-form-urlencoded') . '">';
		#!# This needs to be investigated further:  $html .= "\n" . '<input type="hidden" name="_charset_" />';
		
		# Start the HTML
		$formHtml = '';
		$hiddenHtml = "\n";
		
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
			
			# If colons are set to show, add them
			if ($this->settings['displayColons']) {$elementAttributes['title'] .= ':';}
			
			# If the element is required, and indicators are in use add an indicator
			$elementIsRequired = ($this->settings['requiredFieldIndicator'] && $elementAttributes['required']);
			if ($elementIsRequired) {
				$elementAttributes['title'] .= '&nbsp;*';
			}
			
			# If the form has been posted AND the element has any problems or is empty, add the warning CSS class
			if ($this->formPosted && (($elementAttributes['problems']) || ($elementAttributes['requiredButEmpty']))) {
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
						$formHtml .= "\n" . '<p id="' . $id . '"' . ($elementIsRequired ? " class=\"{$this->settings['requiredFieldClass']}\"" : '') . '>';
						$formHtml .= "\n\t";
						if ($this->settings['displayTitles']) {
							$formHtml .= $elementAttributes['title'] . '<br />';
							if ($displayRestriction) {$formHtml .= "<br /><span class=\"restriction\">(" . ereg_replace ("\n", '<br />', $elementAttributes['restriction']) . ')</span>';}
						}
						$formHtml .= $elementAttributes['html'];
						if ($this->settings['displayDescriptions']) {if ($elementAttributes['description']) {$formHtml .= "<br />\n<span class=\"description\">" . $elementAttributes['description'] . '</span>';}}
						$formHtml .= "\n</p>";
					}
					break;
					
				# Display using divs for CSS layout mode; this is different to paragraphs as the form fields are not conceptually paragraphs
				case 'css':
					$formHtml .= "\n" . '<div class="row" id="' . $id . '"' . ($elementIsRequired ? " class=\"{$this->settings['requiredFieldClass']}\"" : '') . '>';
					if ($elementAttributes['type'] == 'heading') {
						$formHtml .= "\n\t<span class=\"title\">" . $elementAttributes['html'] . '</span>';
					} else {
						$formHtml .= "\n\t";
						if ($this->settings['displayTitles']) {
							if ($displayRestriction) {
								$formHtml .= "<span class=\"label\">";
								$formHtml .= "\n\t\t" . $elementAttributes['title'];
								$formHtml .= "\n\t\t<span class=\"restriction\">(" . ereg_replace ("\n", '<br />', $elementAttributes['restriction']) . ')</span>';
								$formHtml .= "\n\t</span>";
							} else {
								$formHtml .= "<span class=\"label\">" . $elementAttributes['title'] . '</span>';
							}
						}
						$formHtml .= "\n\t<span class=\"data\">" . $elementAttributes['html'] . '</span>';
						if ($this->settings['displayDescriptions']) {if ($elementAttributes['description']) {$formHtml .= "\n\t<span class=\"description\">" . $elementAttributes['description'] . '</span>';}}
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
					$formHtml .= "\n\t" . '<tr class="' . $id . '"' . ($elementIsRequired ? " class=\"{$this->settings['requiredFieldClass']}\"" : '') . '>';
					if ($elementAttributes['type'] == 'heading') {
						# Start by determining the number of columns which will be needed for headings involving a colspan
						$colspan = 1 + ($this->settings['displayTitles']) + ($this->settings['displayDescriptions']);
						$formHtml .= "\n\t\t<td colspan=\"$colspan\">" . $elementAttributes['html'] . '</td>';
					} else {
						$formHtml .= "\n\t\t";
						if ($this->settings['displayTitles']) {
							$formHtml .= "<td class=\"title\">" . ($elementAttributes['title'] == '' ? '&nbsp;' : $elementAttributes['title']);
							if ($displayRestriction) {$formHtml .= "<br />\n\t\t\t<span class=\"restriction\">(" . ereg_replace ("\n", '<br />', $elementAttributes['restriction']) . ")</span>\n\t\t";}
							$formHtml .= '</td>';
						}
						$formHtml .= "\n\t\t<td class=\"data\">" . $elementAttributes['html'] . "</td>";
						if ($this->settings['displayDescriptions']) {$formHtml .= "\n\t\t<td class=\"description\">" . ($elementAttributes['description'] == '' ? '&nbsp;' : $elementAttributes['description']) . '</td>';}
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
		$submitButtonText = $this->settings['submitButtonText'] . (!empty ($this->settings['submitButtonAccesskey']) ? '&nbsp; &nbsp;[Alt+' . $this->settings['submitButtonAccesskey'] . ']' : '');
		$formButtonHtml = '<input value="' . $submitButtonText . '" ' . (!empty ($this->settings['submitButtonAccesskey']) ? "accesskey=\"{$this->settings['submitButtonAccesskey']}\" "  : '') . 'type="' . (!$this->settings['submitButtonImage'] ? 'submit' : "image\" src=\"{$this->settings['submitButtonImage']}\" name=\"submit\" alt=\"{$submitButtonText}") . '" class="button" />';
		if ($this->settings['display'] == 'template') {
			$formHtml = str_replace ($this->displayTemplateElementReplacementsSpecials['SUBMIT'], $formButtonHtml, $formHtml);
		} else {
			$formButtonHtml = "\n\n" . '<p class="submit">' . $formButtonHtml . '</p>';
			$formHtml = ((!$this->settings['submitButtonAtEnd']) ? ($formButtonHtml . $formHtml) : ($formHtml . $formButtonHtml));
		}
		
		# Add in the required field indicator for the template version
		if (($this->settings['display'] == 'template') && ($this->settings['requiredFieldIndicator'])) {
			$formHtml = str_replace ($this->displayTemplateElementReplacementsSpecials['REQUIRED'], $requiredFieldIndicatorHtml, $formHtml);
		}
		
		# Add in a reset button if wanted
		if ($this->settings['resetButton']) {
			$resetButtonHtml = '<input value="' . $this->settings['resetButtonText'] . (!empty ($this->settings['resetButtonAccesskey']) ? '&nbsp; &nbsp;[Alt+' . $this->settings['resetButtonAccesskey'] . ']" accesskey="' . $this->settings['resetButtonAccesskey'] : '') . '" type="reset" class="resetbutton" />';
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
	function getElementProblems ()
	{
		# If the form is posted, return false
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
			
			# If the presentation matrix has a specification for the element (only heading should not do so), merge the setup-assigned output formats over the defaults in the presentation matrix
			if (isSet ($presentationDefaults[$attributes['type']])) {
				$this->elements[$element]['output'] = array_merge ($presentationDefaults[$attributes['type']], $attributes['output']);
				
				# Slightly hacky special case: for a select type, if in multiple mode, use the multiple output format instead
				if ($attributes['type'] == 'select') {
					foreach ($this->elements[$element]['output'] as $outputType => $outputFormat) {
						$indicatorLength = 0 - strlen ($indicator = " [when in 'multiple' mode]");
						if (substr ($outputType, $indicatorLength) == $indicator) {
							$replacementType = substr ($outputType, 0, $indicatorLength);
							if ($this->elements[$element]['multiple']) {
								$this->elements[$element]['output'][$replacementType] = $presentationDefaults[$attributes['type']][$outputType];
							}
							unset ($this->elements[$element]['output'][$outputType]);
						}
					}
				}
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
					'rawcomponents'	=> 'An array with every defined element being assigned as itemName => boolean',
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
					'rawcomponents'	=> 'An array with every defined element being assigned as itemName => boolean',
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
					'rawcomponents'	=> 'An array with every defined element being assigned as autonumber => filename',
					'compiled'		=> 'An array with every successful element being assigned as autonumber => filename',
					'presented'		=> 'Submitted files (and failed uploads) as a human-readable string with the original filenames in brackets',
				),
				'file'				=> array ('rawcomponents', 'presented'),
				'email'				=> array ('presented', 'compiled'),
				'confirmationEmail'	=> array ('presented'),
				'screen'			=> array ('presented'),
				'processing'		=> array ('compiled', 'rawcomponents', 'presented'),
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
		echo $html;
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
		echo $html;
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
				echo "\n\n" . '<p class="error">A confirmation e-mail could not be sent as no address was given.</p>';
				return false;
			}
		}
		
		# Construct the introductory text, including the IP address for the e-mail type
		#!# Make the IP address bit configurable; currently removed: ($outputType == 'email' ? ', from the IP address ' . $_SERVER['REMOTE_ADDR'] : '')
		$introductoryText = ($outputType == 'confirmationEmail' ? $this->settings['confirmationEmailIntroductoryText'] . ($this->settings['confirmationEmailIntroductoryText'] ? "\n\n\n" : '') : $this->settings['emailIntroductoryText'] . ($this->settings['emailIntroductoryText'] ? "\n\n\n" : '')) . ($outputType == 'email' ? 'Below is a submission from the form' :  'Below is a confirmation of (apparently) your submission from the form') . " at \n" . $_SERVER['_PAGE_URL'] . "\nmade at " . date ('g:ia, jS F Y') . ', from the IP address ' . $_SERVER['REMOTE_ADDR'] . '.';
		
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
				$resultLines[] = strip_tags ($this->elements[$name]['title']) . ($outputType == 'email' ? " [$name]" : '') . ":\n" . $presentedData[$name];
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
			echo "\n\n" . '<p class="' . ($success ? 'success' : 'error') . '">' . ($success ? 'A confirmation e-mail has been sent' : 'There was a problem sending a confirmation e-mail') . ' to the address you gave (' . $presentedData[$name] = str_replace ('@', '<span>&#64;</span>', htmlspecialchars ($this->configureResultConfirmationEmailRecipient)) . ').</p>';
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
	 * Function to write the results to a database
	 * @access private
	 */
	#!# Error handling in this function is too basic and needs to be moved higher in the class
	function outputDataDatabase ($presentedData)
	{
		# Connect to the database
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
			
			# Loop through each sub-element
			foreach ($this->form[$name] as $key => $attributes) {
				
				# Assign the eventual name (overwriting the uploaded name if the name is being forced)
				#!# How can we deal with multiple files?
				if ($arguments['forcedFileName']) {
					#!# This is very hacky
					$attributes['name'] = $_FILES[$this->settings['name']]['name'][$name][$key] = $arguments['forcedFileName'];
				}
				
				# Check whether a file already exists
				if (file_exists ($existingFileName = ($arguments['directory'] . $_FILES[$this->settings['name']]['name'][$name][$key]))) {
					
					# Check whether the file being uploaded has the same checksum as the existing file
					if (md5_file ($existingFileName) != md5_file ($_FILES[$this->settings['name']]['tmp_name'][$name][$key])) {
						
						# If version control is enabled, move the old file, appending the date; if the file really cannot be renamed, append the date to the new file instead
						if ($arguments['enableVersionControl']) {
							$timestamp = date ('Ymd-Hms');
							if (!@rename ($existingFileName, $existingFileName . '.replaced-' . $timestamp)) {
								$_FILES[$this->settings['name']]['name'][$name][$key] .= '.forRenamingBecauseCannotMoveOld-' . $timestamp;
							}
							
						/* # If version control is not enabled, give a new name to the new file to prevent the old one being overwritten accidentally
						} else {
							# If a file of the same name but a different checksum exists, append the date and time to the proposed filename
							$_FILES[$this->settings['name']]['name'][$name][$key] .= date ('.Ymd-Hms');
							*/
						}
					}
				}
				
				# Attempt to upload the file
				if (!move_uploaded_file ($_FILES[$this->settings['name']]['tmp_name'][$name][$key], $arguments['directory'] . $_FILES[$this->settings['name']]['name'][$name][$key])) {
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
			
			#!# Consider putting more info into the results (e.g. file size and type)
			
			# Start the compiled result
			$data['presented'] = '';
			$data['compiled'] = array ();
			
			# If there were any succesful uploads, assign the compiled output
			if ($successes) {
				
				# Add each of the files to the master array, appending the location for each
				foreach ($successes as $success => $attributes) {
					$filenames[] = $success;
					$data['compiled'][] = $arguments['directory'] . $success;
				}
				
				# For the compiled version, give the number of files uploaded and their names
				$totalSuccesses = count ($successes);
				$data['presented'] .= $totalSuccesses . ($totalSuccesses > 1 ? ' files' : ' file') . ' (' . implode (', ', $filenames) . ') ' . ($totalSuccesses > 1 ? 'were' : 'was') . ' successfully copied over.';
			}
			
			# If there were any failures, list them also
			if ($failures) {
				$totalFailures = count ($failures);
				$data['presented'] .= ($successes ? ' ' : '') . $totalFailures . ($totalFailures > 1 ? ' files' : ' file') . ' (' . implode (', ', array_keys ($failures)) . ') unfortunately failed to copy over for some unspecified reason.';
			}
			
			# The raw component array out with empty fields upto the number of created subfields
			$data['rawcomponents'] = array_pad ($data['compiled'], $arguments['subfields'], false);
			
			# Assign the output data
			$this->elements[$name]['data'] = $data;
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
	
	
	# Constructor
	function formWidget (&$form, $suppliedArguments, $argumentDefaults, $functionName, $subargument = NULL) {
		
		# Inherit the settings
		$this->settings =& $form->settings;
		
		# Assign the function name
		$this->functionName = $functionName;
		
		# Assign the setup errors array
		$this->formSetupErrors =& $form->formSetupErrors;
		
		# Assign the arguments
		$this->arguments = $this->assignArguments ($suppliedArguments, $argumentDefaults, $functionName, $subargument);
		
		# Register the element name to enable duplicate checking
		$form->registerElementName ($this->arguments['name']);
	}
	
	
	# Function to set the widget's (submitted) value
	function setValue ($value) {
		$this->value = $value;
	}
	
	
	# Function to return the arguments
	function getArguments () {
		return $this->arguments;
	}
	
	
	# Function to return the widget's (submitted but processed) value
	function getValue () {
		return $this->value;
	}
	
	
	# Function to determine if a widget is required but empty
	function requiredButEmpty ()
	{
		return (($this->arguments['required']) && (strlen ($this->value) == 0));
	}
	
	
	# Function to return the widget's problems
	function getElementProblems ($problems) {
		
		#!# Temporary: merge in any problems from the object
		if ($problems) {$this->elementProblems += $problems;}
		
		return $this->elementProblems;
	}
	
	
	# Function to merge the arguments
	function assignArguments ($suppliedArguments, $argumentDefaults, $functionName, $subargument = NULL)
	{
		# Merge the defaults: ensure that arguments with a non-null default value are set (throwing an error if not), or assign the default value if none is specified
		foreach ($argumentDefaults as $argument => $defaultValue) {
			if (is_null ($defaultValue)) {
				if (!isSet ($suppliedArguments[$argument])) {
					$this->formSetupErrors['absent' . ucfirst ($functionName) . ucfirst ($argument)] = "No '$argument' has been set for a specified $functionName field.";
					$arguments[$argument] = $functionName;
				} else {
					$arguments[$argument] = $suppliedArguments[$argument];
				}
				
			# If a subargument is supplied, deal with subarguments
			} elseif ($subargument && ($argument == $subargument)) {
				foreach ($defaultValue as $subArgument => $subDefaultValue) {
					if (is_null ($subDefaultValue)) {
						if (!isSet ($suppliedArguments[$argument][$subArgument])) {
							$this->formSetupErrors['absent' . ucfirst ($fieldType) . ucfirst ($argument) . ucfirst ($subArgument)] = "No '$subArgument' has been set for a specified $argument argument in a specified $fieldType field.";
							$arguments[$argument][$subArgument] = $fieldType;
						} else {
							$arguments[$argument][$subArgument] = $suppliedArguments[$argument][$subArgument];
						}
					} else {
						$arguments[$argument][$subArgument] = (isSet ($suppliedArguments[$argument][$subArgument]) ? $suppliedArguments[$argument][$subArgument] : $subDefaultValue);
					}
				}
				
			# Otherwise assign argument as normal
			} else {
				$arguments[$argument] = (isSet ($suppliedArguments[$argument]) ? $suppliedArguments[$argument] : $defaultValue);
			}
		}
		
		# Return the arguments
		return $arguments;
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
		#$this->form[$name] = ereg_replace ('[^0-9\. ]', '', trim ($this->form[$name]));
		
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
		
		# Re-assign the data
		#!# Remove these
		$this->value = $data;
	}
	
	
	# Perform regexp checks
	#!# Should there be checking for clashes between disallow and regexp, i.e. so that the widget can never submit?
	#!# Should there be checking of disallow and regexp when editable is false, i.e. so that the widget can never submit?
	function regexpCheck ()
	{
		# End if the form is empty; strlen is used rather than a boolean check, as a submission of the string '0' will otherwise fail this check incorrectly
		if (strlen ($this->value) == 0) {return;}
		
		# Regexp checks (for non-e-mail types)
		if (strlen ($this->arguments['regexp'])) {
			if (!ereg ($this->arguments['regexp'], $this->value)) {
				$this->elementProblems['failsRegexp'] = "The submitted information did not match a specific pattern required for the {$this->arguments['name']} section.";
			}
		}
		
		# 'disallow' regexp checks (for text types)
		if (strlen ($this->arguments['disallow'])) {
			if (ereg ($this->arguments['disallow'], $this->value)) {
				$this->elementProblems['failsDisallow'] = "The submitted information matched a disallowed pattern for the {$this->arguments['name']} section.";
			}
		}
		
		# E-mail check (for e-mail type)
		if ($this->functionName == 'email') {
			if (!application::validEmail ($this->value)) {
				$this->elementProblems['invalidEmail'] = 'The e-mail address you gave appears to be invalid.';
			}
		}
	}
}


#!# Make the file specification of the form more user-friendly (e.g. specify / or ./ options)
#!# Do a single error check that the number of posted elements matches the number defined; this is useful for checking that e.g. hidden fields are being posted
#!# Need to add basic protection for ensuring that form sub-elements submitted (in selectable types) are in the list of available values; this has already been achieved for checkboxes, relatively easily
#!# Add form setup checking validate input types like cols= is numeric, etc.
#!# Add a warnings flag in the style of the errors flagging to warn of changes which have been made silently
#!# Need to add configurable option (enabled by default) to add headings to new CSV when created
#!# Ideally add a catch to prevent the same text appearing twice in the errors box (e.g. two widgets with "details" as the descriptive text)
#!# Enable maximums to other fields
#!# Complete the restriction notices
#!# Add a CSS class to each type of widget so that more detailed styling can be applied
#!# Enable locales, e.g. ordering month-date-year for US users
#!# Consider language localisation (put error messages into a global array)
#!# Add in <span>&#64;</span> for on-screen e-mail types
#!# Add standalone database-writing
#!# Apache setup needs to be carefully tested, in conjunction with php.net/ini-set and php.net/configuration.changes
#!# Add links to the id="$name" form elements in cases of USER errors (not for the templating mode though)
#!# Need to prevent the form code itself being overwritable by uploads or CSV writing, by doing a check on the filenames
#!# Not all $widgetHtml declarations have an id="" given (make sure it is $this>cleanId'd though)
#!# Add <label> and (where appropriate) <fieldset> support throughout - see also http://www.aplus.co.yu/css/styling-form-fields/ ; http://www.bobbyvandersluis.com/articles/formlayout.php ; http://www.simplebits.com/notebook/2003/09/16/simplequiz_part_vi_formatting.html ; http://www.htmldog.com/guides/htmladvanced/forms/ ; checkbox & radiobutton have some infrastructure written (but commented out) already
#!# Prevent insecure bcc:ing as per http://lists.evolt.org/harvest/detail.cgi?w=20050725&id=6342
#!# Full support for all attributes listed at http://www.w3schools.com/tags/tag_input.asp e.g. accept="list_of_mime_types" for type=file
#!# Suggestions at http://www.onlamp.com/pub/a/php/2004/08/26/PHPformhandling.html
#!# merge autoCenturyConversionEnabled and autoCenturyConversionLastYear
# Remove display_errors checking misfeature or consider renaming as disableDisplayErrorsCheck
# Enable specification of a validation function

# Version 2 feature proposals
#!# Full object orientation - change the form into a package of objects
#!#		Change each input type to an object, with a series of possible checks that can be implemented - class within a class?
#!# 	Change the output methods to objects
#!# Allow multiple carry-throughs, perhaps using formCarried[$formNumber][...]: Add carry-through as an additional array section; then translate the additional array as a this-> input to hidden fields.
#!# Enable javascript as an option
#!# 	Use ideas in http://www.sitepoint.com/article/1273/3 for having js-validation with an icon
#!# 	Style like in http://www.sitepoint.com/examples/simpletricks/form-demo.html [linked from http://www.sitepoint.com/article/1273/3]
#!# Add AJAX validation flag See: http://particletree.com/features/degradable-ajax-form-validation/ (but modified version needed because this doesn't use Unobtrusive DHTML - see also http://particletree.com/features/a-guide-to-unobtrusive-javascript-validation/ )
#!# Self-creating form mode
#!# Postponed files system

?>