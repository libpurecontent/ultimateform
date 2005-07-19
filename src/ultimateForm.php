<?php

/**
 * A class for the easy creation of webforms.
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
 * - Templating mechanism
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
 * @author	{@link http://www.geog.cam.ac.uk/contacts/webmaster.html Martin Lucas-Smith}, University of Cambridge 2003-4
 * @copyright Copyright � 2003-5, Martin Lucas-Smith, University of Cambridge
 * @version 0.99b3
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
	var $duplicatedelementNames = array ();		// The array to hold any duplicated form field names
	var $formSetupErrors = array ();			// Array of form setup errors, to which any problems can be added
	var $elementProblems = array ();			// Array of submitted element problems
	
	# State control
	var $formPosted;							// Flag for whether the form has been posted
	var $formDisplayed = false;					// Flag for whether the form has been displayed
	var $formSetupOk = false;					// Flag for whether the form has been set up OK
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
	
	# Supported output types
	var $supportedTypes = array ('file', 'email', 'confirmationEmail', 'screen', 'processing');
	var $displayTypes = array ('tables', 'css', 'paragraphs', 'templatefile');
	
	# Constants
	var $timestamp;
	var $minimumPhpVersion = 4.3; // md5_file requires 4.2+; file_get_contents is 4.3+
	
	# Specify available arguments as defaults or as NULL (to represent a required argument)
	var $argumentDefaults = array (
		'name'							=> 'form',									# Name of the form
		'div'							=> 'ultimateform',							# The value of <div class=""> which surrounds the entire output (or false for none)
		'displayPresentationMatrix'		=> false,									# Whether to show the presentation defaults
		'displayTitles'					=> true,									# Whether to show user-supplied titles for each widget
		'displayDescriptions'			=> true,									# Whether to show user-supplied descriptions for each widget
		'displayRestrictions'			=> true,									# Whether to show/hide restriction guidelines
		'display'						=> 'tables',								# Whether to display the form using 'tables', 'css' (CSS layout) 'paragraphs' or 'template'
		'displayTemplate'				=> '',										# Either a filename or a (long) string containing placemarkers
		'displayTemplatePatternWidget'	=> '{%element}',							# The pattern used for signifying element name widget positions when templating
		'displayTemplatePatternLabel'	=> '{[%element]}',							# The pattern used for signifying element name label positions (optional) when templating
		'displayTemplatePatternSpecial'	=> '{[[%element]]}',						# The pattern used for signifying element name special item positions (e.g. submit, reset, problems) when templating
		'debug'							=> false,									# Whether to switch on debugging
		'developmentEnvironment'		=> false,									# Whether to run in development mode
		'displayColons'					=> true,									# Whether to show colons after the initial description
		'whiteSpaceTrimSurrounding'		=> true,									# Whether to trim surrounding white space in any forms which are submitted
		'whiteSpaceCheatAllowed'		=> false,									# Whether to allow people to cheat submitting whitespace only in required fields
		'formCompleteText'				=> 'Many thanks for your input.',			# The form completion text
		'displayFormCompleteText'		=> true,									# Whether to show the form complete text when completed
		'submitButtonAtEnd'				=> true,									# Whether the submit button appears at the end or the start of the form
		'submitButtonText'				=> 'Submit!',								# The form submit button text
		'submitButtonAccesskey'			=> 's',										# The form submit button accesskey
		'submitButtonImage'				=> false,									# Location of an image to replace the form submit button
		'resetButton'					=> false,									# Whether the reset button is visible (note that this must be switched on in the template mode to appear, even if the reset placemarker is given)
		'resetButtonText'				=> 'Clear changes',							# The form reset button
		'resetButtonAccesskey'			=> 'r',										# The form reset button accesskey
		'warningMessage'				=> 'The highlighted items have not been completed successfully.',	# The form incompletion message
		'requiredFieldIndicator'		=> true,									# Whether the required field indicator is to be displayed (top / bottom/true / false) (note that this must be switched on in the template mode to appear, even if the reset placemarker is given)
		'submitTo'						=> false,									# The form processing location if being overriden
		'autoCenturyConversionEnabled'	=> true,									# Whether years entered as two digits should automatically be converted to four
		'autoCenturyConversionLastYear'	=> 69,										# The last two figures of the last year where '20' is automatically prepended
		'nullText'						=> 'Please select',							# The 'null' text for e.g. selection boxes
		'opening'						=> false,									# Optional starting datetime as an SQL string
		'closing'						=> false,									# Optional closing datetime as an SQL string
		'validUsers'					=> false,									# Optional valid user(s) - if this is set, a user will be required. To set, specify string/array of valid user(s), or '*' to require any user
		'user'							=> false,									# Explicitly-supplied username (if none specified, will check for REMOTE_USER being set
		'userKey'						=> false,									# Whether to log the username, as the key
		'loggedUserUnique'				=> false,									# Run in user-uniqueness mode, making the key of any CSV the username and checking for resubmissions
		'timestamping'					=> false,									# Add a timestamp to any CSV entry
	);
	
	# Temporary API compatibility fixes
	var $apiFix = array (
		// Global paramaters:
		'formName' => 'name',
		'showPresentationMatrix' => 'displayPresentationMatrix',
		'showFormCompleteText' => 'displayFormCompleteText',
		'requiredFieldIndicatorDisplay' => 'requiredFieldIndicator',
		'resetButtonVisible' => 'resetButton',
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
		$suppliedArguments = $this->apiFix ($suppliedArguments);
		foreach ($this->argumentDefaults as $argument => $defaultValue) {
			$this->{$argument} = (isSet ($suppliedArguments[$argument]) ? $suppliedArguments[$argument] : $defaultValue);
			#!# Temporary while refactoring - need to REMOVE $this->{$argument} above by moving everything to $this->settings[$argument] instead
			$this->settings[$argument] = $this->{$argument};
		}
		
		# Define the submission location (as _SERVER cannot be set in a class variable declaration)
		if ($this->submitTo === false) {$this->submitTo = $_SERVER['REQUEST_URI'];}
		
		# Ensure the userlist is an array, whether empty or otherwise
		$this->validUsers = application::ensureArray ($this->validUsers);
		
		# If no user is supplied, attempt to obtain the REMOTE_USER (if one exists) as the default
		if (!$this->user) {$this->user = (isSet ($_SERVER['REMOTE_USER']) ? $_SERVER['REMOTE_USER'] : false);}
		
		# Assign whether the form has been posted or not
		$this->formPosted = (isSet ($_POST[$this->name]));
		
		# Add in the hidden security fields if required, having verified username existence if relevant; these need to go at the start so that any username is set as the key
		$this->addHiddenSecurityFields ();
		
		# Import the posted data if the form is posted; this has to be done initially otherwise the input widgets won't have anything to reference
		if ($this->formPosted) {$this->form = $_POST[$this->name];}
		
		# If there are files posted, merge the multi-part posting of files to the main form array, effectively emulating the structure of a standard form input type
		if (!empty ($_FILES[$this->name])) {$this->mergeFilesIntoPost ();}
	}
	
	
	# Function to fix arguments under the old API
	function apiFix ($arguments)
	{
		# Loop through the compatibility fixes
		foreach ($this->apiFix as $old => $new) {
			
			# Replace the old argument with the new if found
			if (isSet ($arguments[$old])) {
				$arguments[$new] = $arguments[$old];
				unset ($arguments[$old]);
			}
		}
		
		# Return the fixed arguments
		return $arguments;
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
			'title'					=> '',		# Introductory text
			'description'			=> '',		# Description text
			'output'				=> array (),# Presentation format
			'required'				=> false,	# Whether required or not
			'enforceNumeric'		=> false,	# Whether to enforce numeric input or not (optional; defaults to false) [ignored for e-mail type]
			'size'					=> 30,		# Visible size (optional; defaults to 30)
			'maxlength'				=> '',		# Maximum length (optional; defaults to no limit)
			'default'				=> '',		# Default value (optional)
			'regexp'				=> '',		# Regular expression against which the submission must validate (optional) [ignored for e-mail type]
		);
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, $functionName);
		
		$arguments = $widget->getArguments ();
		
		# Register the element name to enable duplicate checking
		$this->registerElementName ($arguments['name']);
		
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
		
		# Check whether the field satisfies any requirement for a field to be required
		$requiredButEmpty = $widget->requiredButEmpty ();
		
		
		$elementValue = $widget->getValue ();
		
		
		# Assign the initial value if the form is not posted (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid)
		if (!$this->formPosted) {$elementValue = $arguments['default'];}
		
		# Describe restrictions on the widget
		if ($arguments['enforceNumeric'] && ($functionName != 'email')) {$restriction = 'Must be numeric';}
		if ($functionName == 'email') {$restriction = 'Must be valid';}
		
		# Define the widget's core HTML
		$widgetHtml = '<input name="' . $this->name . "[{$arguments['name']}]\" type=\"" . ($functionName == 'password' ? 'password' : 'text') . "\" size=\"{$arguments['size']}\"" . ($arguments['maxlength'] != '' ? " maxlength=\"{$arguments['maxlength']}\"" : '') . " value=\"" . htmlentities ($elementValue) . '" />';
		
		# Re-assign back the value
		$this->form[$arguments['name']] = $elementValue;
		
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
			'restriction' => (isSet ($restriction) ? $restriction : false),
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $arguments['required'],
			'requiredButEmpty' => $requiredButEmpty,
			'suitableAsEmailTarget' => ($functionName == 'email'),
			'output' => $arguments['output'],
			'data' => (isSet ($data) ? $data : NULL),
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
			'title'					=> '',		# Introductory text
			'description'			=> '',		# Description text
			'output'				=> array (),# Presentation format
			'required'				=> false,	# Whether required or not
			'enforceNumeric'		=> false,	# Whether to enforce numeric input or not (optional; defaults to false)
			'cols'					=> 30,		# Number of columns (optional; defaults to 30)
			'rows'					=> 5,		# Number of rows (optional; defaults to 30)
			'default'				=> '',		# Default value (optional)
			'regexp'				=> '',		# Regular expression(s) against which the submission must (all) validate (optional)
			'mode'					=> 'normal',	# Special mode: normal/lines/coordinates
		);
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, __FUNCTION__);
		
		$arguments = $widget->getArguments ();
		
		# Register the element name to enable duplicate checking
		$this->registerElementName ($arguments['name']);
		
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
		
		# Perform validity tests if anything has been submitted and regexp(s) are supplied
		if ($elementValue && ($arguments['regexp'] || $arguments['mode'] == 'coordinates')) {
			
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
			}
			
			# If any problem lines are found, construct the error message for this
			if (isSet ($problemLines)) {
				$elementProblems['failsRegexp'] = (count ($problemLines) > 1 ? 'Rows ' : 'Row ') . implode (', ', $problemLines) . (count ($problemLines) > 1 ? ' do not' : ' does not') . ' match a specified pattern required for this section' . (($arguments['mode'] == 'coordinates') ? ', ' . ((count ($arguments['regexp']) > 1) ? 'including' : 'namely' ) . ' the need for two co-ordinates per line' : '') . '.';
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
		
		# Define the widget's core HTML
		$widgetHtml = '<textarea name="' . $this->name . "[{$arguments['name']}]\" id=\"" . $this->name . $this->cleanId ("[{$arguments['name']}]") . "\" cols=\"{$arguments['cols']}\" rows=\"{$arguments['rows']}\">" . htmlentities ($elementValue) . '</textarea>';
		
		# Re-assign back the value
		$this->form[$arguments['name']] = $elementValue;
		
		# Get the posted data
		if ($this->formPosted) {
			
			# For presented, assign the raw data directly to the output array
			$data['presented'] = $this->form[$arguments['name']];
			
			# For raw components:
			switch ($arguments['mode']) {
					case 'coordinates':
					
					# For the raw components version, split by the newline then by the whitespace, presented as an array (x, y)
					$lines = explode ("\n", $this->form[$arguments['name']]);
					foreach ($lines as $autonumber => $line) {
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
			'restriction' => (isSet ($restriction) ? $restriction : false),
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $arguments['required'],
			'requiredButEmpty' => $requiredButEmpty,
			'suitableAsEmailTarget' => false,
			'output' => $arguments['output'],
			'data' => (isSet ($data) ? $data : NULL),
		);
	}
	
	
	/**
	 * Create a rich text editor field based on FCKeditor 2.0
	 * @param array $arguments Supplied arguments - see template
	 */
	 
	/*
	
	# Note: make sure file_uploads is on in the upload location!
	
	The following source code alterations must be made to FCKeditor 2.0
	
	1. Customised configurations which cannot go in the PHP at present
	Add the supplied file /_fckeditor/fckconfig-customised.js
	
	2. Add in main fckconfig.js (NOT elsewhere) after DocType [see http://sourceforge.net/tracker/index.php?func=detail&aid=1199631&group_id=75348&atid=543653  and  https://sourceforge.net/tracker/index.php?func=detail&aid=1200670&group_id=75348&atid=543653 ]
	// Prevent left-right scrollbars
	FCKConfig.DocType = '' ;
	
	3. Open /_fckeditor/editor/filemanager/browser/default/connectors/php/config.php and change:
	$Config['Enabled'] = true ;
	$Config['UserFilesPath'] = '' ;
	
	4. In /_fckeditor/editor/filemanager/browser/default/connectors/php/io.php: add at the start of GetUrlFromPath() the line:
	#MLS# Don't differentiate locations based on the resource type
	$resourceType = '';
	
	5. In /_fckeditor/editor/filemanager/browser/default/connectors/php/io.php: add at the start of ServerMapFolder() the line:
	#MLS# Don't differentiate locations based on the resource type
	$resourceType = '';
	
	6. In /_fckeditor/editor/filemanager/browser/default/connectors/php/io.php: add at the start of CreateServerFolder() the line:
	#MLS# Ensure the folder path has no double-slashes, or mkdir may fail on certain platforms
	while (strpos ($folderPath, '//') !== false) {$folderPath = str_replace ('//', '/', $folderPath);}
	
	7. In /_fckeditor/editor/filemanager/browser/default/connectors/php/io.php: add at the start of GetRootPath() the line:
	#MLS# Return the document root instead of (incorrectly) trying to work it out
	return $_SERVER['DOCUMENT_ROOT'];
	
	
	The following are experienced deficiencies in FCKeditor 2.0 [Final release]
	- Undo/redo is a bit sporadic, but better than in earlier versions (IE6, Firefox)
	- Format box doesn't update to current item (IE6)	http://sourceforge.net/tracker/index.php?func=detail&aid=1187220&group_id=75348&atid=543653
	- CSS underlining inheritance seems wrong (IE6?, Firefox?)
	- API deficiencies: DocType = '', FormatIndentator = "\t", ToolbarSets all have to be set outside PHP
	
	The following are experienced deficiencies in the EARLIER FCKeditor 2.0 FC
	- Undo/redo doesn't work (IE6)		http://sourceforge.net/tracker/index.php?func=detail&aid=1214125&group_id=75348&atid=543653
	- Anchor symbol doesn't display (IE6, Firefox)		http://sourceforge.net/tracker/index.php?func=detail&aid=1202468&group_id=75348&atid=543653
	- Format box doesn't update to current item (IE6)	http://sourceforge.net/tracker/index.php?func=detail&aid=1187220&group_id=75348&atid=543653
	- CSS underlining inheritance seems wrong (IE6?, Firefox)	http://sourceforge.net/tracker/index.php?func=detail&aid=1230485&group_id=75348&atid=543653
	- API deficiencies: DocType = '', FormatIndentator = "\t", ToolbarSets all have to be set outside PHP
	
	The following are experienced deficiencies in the EARLIER FCKeditor 2.0 Beta3
	- Cut/paste row doesn't show (Firefox)
	- Undo/redo doesn't work (IE6, Firefox)
	- Anchor symbol doesn't display (Firefox)
	- Format box doesn't update to current item (IE6)
	- CSS underlining inheritance seems wrong (Firefox)
	- Arrow keys (Firefox)
	- Copy/paste buttons usable (Firefox)
	- API deficiencies: DocType = '', FormatIndentator = "\t", ToolbarSets all have to be set outside PHP
	
	*/
	
	function richtext ($suppliedArguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentDefaults = array (
			'name'					=> NULL,	# Name of the element
			'title'					=> NULL,		# Introductory text
			'description'			=> '',		# Description text
			'output'				=> array (),# Presentation format
			'required'				=> false,	# Whether required or not
			'width'					=> '100%',		# Width
			'height'				=> '400px',		# Height
			'default'				=> '',		# Default value (optional)
			'editorBasePath'		=> '/_fckeditor/',	# Location of the editor files
			'editorToolbarSet'		=> 'pureContent',	# Editor toolbar set
			'editorConfig'				=> array (	# Editor configuration
				# 'DocType' => '',	// Prevent left-right scrollbars	// Has to go in the main config file (not customised file or PHP constructor)
				'CustomConfigurationsPath' => '/_fckeditor/fckconfig-customised.js',
				'FontFormats'			=> 'p;h1;h2;h3;h4;h5;h6;pre',
				'UserFilesPath'			=> '/',
				'EditorAreaCSS'			=> '',
				'BaseHref'				=> '',
				#'FormatIndentator'		=> "\t",
				'GeckoUseSPAN'			=> false,	#!# Even in .js version this seems to have no effect
				'StartupFocus'			=> false,
				'ToolbarCanCollapse'	=> false,
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
				#!# Consider finding a way of getting the new MCPUK browser working - the hard-coded paths are a real problem with it at present
				'LinkBrowserURL'		=> '/_fckeditor/editor/filemanager/browser/default/browser.html?Connector=connectors/php/connector.php',
				'ImageBrowserURL'		=> '/_fckeditor/editor/filemanager/browser/default/browser.html?Type=Image&Connector=connectors/php/connector.php',
			),
		);
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, __FUNCTION__, $subargument = 'editorConfig');
		
		$arguments = $widget->getArguments ();
		
		# Register the element name to enable duplicate checking
		$this->registerElementName ($arguments['name']);
		
		# Obtain the value of the form submission (which may be empty)
		$widget->setValue (isSet ($this->form[$arguments['name']]) ? $this->form[$arguments['name']] : '');
		
		# Handle whitespace issues
		$widget->handleWhiteSpace ();
		
		# Check whether the field satisfies any requirement for a field to be required
		$requiredButEmpty = $widget->requiredButEmpty ();
		
		$elementValue = $widget->getValue ();
		
		# Assign the initial value if the form is not posted (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid)
		if (!$this->formPosted) {$elementValue = $arguments['default'];}
		
		# Define the widget's core HTML by instantiating the richtext editor module and setting required options
		require_once ('fckeditor.php');
		$editor = new FCKeditor ("{$this->name}[{$arguments['name']}]");
		$editor->BasePath	= $arguments['editorBasePath'];
		$editor->Width		= $arguments['width'];
		$editor->Height		= $arguments['height'];
		$editor->ToolbarSet	= $arguments['editorToolbarSet'];
		$editor->Value		= $elementValue;
		$editor->Config		= $arguments['editorConfig'];
		$widgetHtml = $editor->CreateHtml ();
		
		# Re-assign back the value
		$this->form[$arguments['name']] = $elementValue;
		
		# Get the posted data
		if ($this->formPosted) {
			
			# Clean the HTML
			$data['presented'] = $this->richtextClean ($this->form[$arguments['name']]);
		}
		
		# Add the widget to the master array for eventual processing
		$this->elements[$arguments['name']] = array (
			'type' => __FUNCTION__,
			'html' => $widgetHtml,
			'title' => $arguments['title'],
			'description' => $arguments['description'],
			'restriction' => (isSet ($restriction) ? $restriction : false),
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $arguments['required'],
			'requiredButEmpty' => $requiredButEmpty,
			'suitableAsEmailTarget' => false,
			'output' => $arguments['output'],
			'data' => (isSet ($data) ? $data : NULL),
		);
	}
	
	
	# Function to clean the content
	#!# More tidying needed
	function richtextClean ($content)
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
	 * Create a select (drop-down) box widget
	 * @param array $arguments Supplied arguments - see template
	 */
	function select ($suppliedArguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentDefaults = array (
			'name'					=> NULL,	# Name of the element
			'values'				=> array (),# Simple array of selectable values
			'title'					=> '',		# Introductory text
			'description'			=> '',		# Description text
			'output'				=> array (),# Presentation format
			'multiple'				=> false,	# Whether to create a multiple-mode select box
			'required'		=> 0,		# The minimum number which must be selected (defaults to 0)
			'size'			=> 1,		# Number of rows (optional; defaults to 1)
			'default'				=> array (),# Pre-selected item(s)
			'forceAssociative'		=> false,	# Force the supplied array of values to be associative
			'nullText'				=> $this->nullText,	# Override null text for a specific select widget
		);
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, __FUNCTION__);
		
		$arguments = $widget->getArguments ();
		
		# Register the element name to enable duplicate checking
		$this->registerElementName ($arguments['name']);
		
		# Obtain the value of the form submission (which may be empty)
		$widget->setValue (isSet ($this->form[$arguments['name']]) ? $this->form[$arguments['name']] : array ());
		
		$elementValue = $widget->getValue ();
		
		# Check that the array of values is not empty
		#!# Only run other checks below if this error isn't thrown
		if (empty ($arguments['values'])) {$this->formSetupErrors['selectNoValues'] = 'No values have been set as selection items.';}
		
		# Check that the given minimum required is not more than the number of items actually available
		$totalSubItems = count ($arguments['values']);
		if ($arguments['required'] > $totalSubItems) {$this->formSetupErrors['selectMinimumMismatch'] = "The required minimum number of items which must be selected (<strong>{$arguments['required']}</strong>) specified is above the number of select items actually available (<strong>$totalSubItems</strong>).";}
		
		# Ensure the initial value(s) is an array, even if only an empty one, converting if necessary
		$arguments['default'] = application::ensureArray ($arguments['default']);
		
		#!# Currently can set minimum to 2 while multiple is off
		
		# Ensure that there cannot be multiple initial values set if the multiple flag is off
		$totalDefaults = count ($arguments['default']);
		if ((!$arguments['multiple']) && ($totalDefaults > 1)) {
			$this->formSetupErrors['defaultTooMany'] = "In the <strong>{$arguments['name']}</strong> element, $totalDefaults total initial values were assigned but the form has been set up to allow only one item to be selected by the user.";
		}
		
		# Check whether the array is an associative array
		$valuesAreAssociativeArray = (application::isAssociativeArray ($arguments['values']) || $arguments['forceAssociative']);
		$submittableValues = ($valuesAreAssociativeArray ? array_keys ($arguments['values']) : array_values ($arguments['values']));
		
		# Special syntax to set the value of a URL-supplied GET value as the initial value; if the supplied item is not present, ignore it; otherwise replace the default(s) array with the single selected item
		#!# Way of applying more than one item?
		#!# Apply this to checkboxes and radio buttons also
		#!# Need to make 'url:$' in the values array not allowable as a genuine option
		if (isSet ($arguments['default'][0])) {
			$identifier = 'url:$';
			if (substr ($arguments['default'][0], 0, strlen ($identifier)) == $identifier) {
				$urlArgumentKey = substr ($arguments['default'][0], strlen ($identifier));
				$arguments['default'] = array (application::urlSuppliedValue ($urlArgumentKey, $submittableValues));
			} else {
				
				# Ensure that all initial values are in the array of values
				foreach ($arguments['default'] as $defaultValue) {
					if (!in_array ($defaultValue, $submittableValues)) {
						$missingValues[] = $defaultValue;
					}
				}
				if (isSet ($missingValues)) {
					$totalMissingValues = count ($missingValues);
					$this->formSetupErrors['defaultMissingFromValues'] = "In the <strong>{$arguments['name']}</strong> element, the default " . ($totalMissingValues > 1 ? 'values ' : 'value ') . implode (', ', $missingValues) . ($totalMissingValues > 1 ? ' were' : ' was') . ' not found in the list of available items for selection by the user.';
				}
			}
		}
		
		# Ensure that the 'null' text does not clash with any items in the array of values
		if (in_array ($arguments['nullText'], $submittableValues)) {
			$this->formSetupErrors['defaultNullClash'] = "In the <strong>{$arguments['name']}</strong> element, the null text ('{$arguments['nullText']}') clashes with one of list of available items for selection by the user. One or the other must be changed.";
		}
		
		# Clear the null text if it appears, or empty submissions
		#!# Need to modify this as the null text should never be a submitted value now
		foreach ($elementValue as $key => $value) {
			#!# Is the empty ($value) check being done for other similar elements to prevent empty submissions?
			if (($elementValue[$key] == $arguments['nullText']) || (empty ($value))) {
				unset ($elementValue[$key]);
				break;
			}
		}
		
		# Emulate the need for the field to be 'required', i.e. the minimum number of fields is greater than 0
		$required = ($arguments['required'] > 0);
		
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
		
		# Determine whether this field is suitable as the target for an e-mail and, if so, whether a suffix is required
		#R# This can become ternary, or make $arguments['multiple'] / $arguments['required'] as arguments to suitableAsEmailTarget
		$suitableAsEmailTarget = false;
		#!# Apply this to checkboxes
		if ((!$arguments['multiple']) && ($arguments['required'] == 1)) {
			$suitableAsEmailTarget = $this->suitableAsEmailTarget ($submittableValues);
		}
		
		# Define the widget's core HTML
		$widgetHtml = "\n\t\t\t<select name=\"" . $this->name . "[{$arguments['name']}][]\"" . (($arguments['multiple']) ? ' multiple="multiple"' : '') . " size=\"{$arguments['size']}\">";
		#!# Does this now mean that a check for submissions of $arguments['nullText'] is no longer required, as the value will be "" ?
		$widgetHtml .= "\n\t\t\t\t" . '<option value="">' . $arguments['nullText'] . '</option>';
		foreach ($arguments['values'] as $key => $value) {
			$widgetHtml .= "\n\t\t\t\t" . '<option value="' . htmlentities (($valuesAreAssociativeArray ? $key : $value)) . '"' . (in_array (($valuesAreAssociativeArray ? $key : $value), $elementValue) ? ' selected="selected"' : '') . '>' . htmlentities ($value) . '</option>';
		}
		$widgetHtml .= "\n\t\t\t</select>\n\t\t";
		
		# Re-assign back the value
		$this->form[$arguments['name']] = $elementValue;
		
		# Get the posted data
		if ($this->formPosted) {
			
			# For the component array, loop through each defined element name and assign the boolean value for it
			foreach ($arguments['values'] as $key => $value) {
				#!# $submittableValues is defined above and is similar: refactor to remove these lines
				$submittableValue = ($valuesAreAssociativeArray ? $key : $value);
				$data['rawcomponents'][$submittableValue] = (in_array ($submittableValue, $this->form[$arguments['name']]));
			}
			
			# For the compiled version, separate the compiled items by a comma-space
			$data['compiled'] = implode (",\n", $this->form[$arguments['name']]);
			
			# For the presented version, substitute the visible text version used for the actual value if necessary
			#R# Can this be compbined with the compiled and the use of array_keys/array_values to simplify this?
			if (!$valuesAreAssociativeArray) {
				$data['presented'] = $data['compiled'];
			} else {
				
				$chosen = array ();
				foreach ($this->form[$arguments['name']] as $key => $value) {
					if (isSet ($arguments['values'][$value])) {
						$chosen[] = $arguments['values'][$value];
					}
				}
				$data['presented'] = implode (",\n", $chosen);
			}
		}
		
		# Add the widget to the master array for eventual processing
		$this->elements[$arguments['name']] = array (
			'type' => __FUNCTION__,
			'html' => $widgetHtml,
			'title' => $arguments['title'],
			'description' => $arguments['description'],
			'restriction' => (isSet ($restriction) ? $restriction : false),
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $required,
			'requiredButEmpty' => $requiredButEmpty,
			'suitableAsEmailTarget' => $suitableAsEmailTarget,
			'output' => $arguments['output'],
			'data' => (isSet ($data) ? $data : NULL),
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
			'values'				=> array (),# Simple array of selectable values
			'title'					=> '',		# Introductory text
			'description'			=> '',		# Description text
			'output'				=> array (),# Presentation format
			'required'				=> false,	# Whether required or not
			'default'				=> array (),# Pre-selected item
			'nullText'				=> $this->nullText,	# Override null text for a specific select widget (if false, the master value is assumed)
		);
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, __FUNCTION__);
		
		$arguments = $widget->getArguments ();
		
		# Register the element name to enable duplicate checking
		$this->registerElementName ($arguments['name']);
		
		# Obtain the value of the form submission (which may be empty)
		$widget->setValue (isSet ($this->form[$arguments['name']]) ? $this->form[$arguments['name']] : '');
		
		# Check whether the field satisfies any requirement for a field to be required
		$requiredButEmpty = $widget->requiredButEmpty ();
		
		$elementValue = $widget->getValue ();
		
		# Check that the array of values is not empty
		if (empty ($arguments['values'])) {$this->formSetupErrors['radiobuttonsNoValues'] = 'No values have been set for the set of radio buttons.';}
		
		# Check whether the array is an associative array
		$valuesAreAssociativeArray = application::isAssociativeArray ($arguments['values']);
		$submittableValues = ($valuesAreAssociativeArray ? array_keys ($arguments['values']) : array_values ($arguments['values']));
		
		# Ensure that the initial value, if one is set, is in the array of values
		#!# Should the initial value being set be the 'real' or visible value when using an associative array?
		if ((!empty ($arguments['default'])) && (!in_array ($arguments['default'], $submittableValues))) {
			$this->formSetupErrors['defaultMissingFromValuesArray'] = "In the <strong>{$arguments['name']}</strong> element, the initial value was not found in the list of available items for selection by the user.";
		}
		
		# Ensure that the 'null' text does not clash with any items in the array of values
		#!# Does this matter, if the value is not submitted?
		if (in_array ($arguments['nullText'], $arguments['values'])) {
			$this->formSetupErrors['defaultNullClash'] = "In the <strong>{$arguments['name']}</strong> element, the null text ('{$arguments['nullText']}') clashes with one of list of available items for selection by the user. One or the other must be changed.";
		}
		
		# Assign the initial value if the form is not posted (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid)
		if (!$this->formPosted) {$elementValue = $arguments['default'];}
		
		# If it's not a required field, add a null field here
		if (!$arguments['required']) {array_unshift ($arguments['values'], $arguments['nullText']);}
		
		# Define the widget's core HTML
		$widgetHtml = '';
		foreach ($arguments['values'] as $key => $value) {
			$elementId = $this->cleanId ("{$arguments['name']}_{$value}");
			$submittableValue = ($valuesAreAssociativeArray ? $key : $value);
			$widgetHtml .= "\n\t\t\t" . '<input type="radio" name="' . $this->name . "[{$arguments['name']}]\"" . ' value="' . htmlentities ($submittableValue) . '"' . (($submittableValue == $elementValue) ? ' checked="checked"' : '') . ' id="' . $elementId . '"' . " /><label for=\"" . $elementId . '">' . htmlentities ($value) . "</label><br />";
		}
		$widgetHtml .= "\n\t\t";
		
		# Clear a null submission (this must come after the HTML is defined)
		if ($elementValue == $arguments['nullText']) {$elementValue = '';}
		
		# Re-assign back the value
		$this->form[$arguments['name']] = $elementValue;
		
		# Get the posted data
		if ($this->formPosted) {
			
			# Check whether the array is an associative array
			$valuesAreAssociativeArray = application::isAssociativeArray ($arguments['values']);
			$submittableValues = ($valuesAreAssociativeArray ? array_keys ($arguments['values']) : array_values ($arguments['values']));
			
			# For the rawcomponents version, create an array with every defined element being assigned as itemName => boolean
			$data['rawcomponents'] = array ();
			foreach ($arguments['values'] as $key => $value) {
				$submittableValue = ($valuesAreAssociativeArray ? $key : $value);
				$data['rawcomponents'][$submittableValue] = ($this->form[$arguments['name']] == $submittableValue);
			}
			
			# Take the selected option and ensure that this is in the array of available values
			#!# What if it's not? - This check should be moved up higher
			$data['compiled'] = (in_array ($this->form[$arguments['name']], $submittableValues) ? $this->form[$arguments['name']] : '');
			
			# For the presented version, substitute the visible text version used for the actual value if necessary
			$data['presented'] = (in_array ($this->form[$arguments['name']], $submittableValues) ? ($valuesAreAssociativeArray ? $arguments['values'][$this->form[$arguments['name']]] : $this->form[$arguments['name']]) : '');
		}
		
		# Add the widget to the master array for eventual processing
		$this->elements[$arguments['name']] = array (
			'type' => __FUNCTION__,
			'html' => $widgetHtml,
			'title' => $arguments['title'],
			'description' => $arguments['description'],
			'restriction' => (isSet ($restriction) ? $restriction : false),
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $arguments['required'],
			'requiredButEmpty' => $requiredButEmpty,
			'suitableAsEmailTarget' => $arguments['required'],
			'output' => $arguments['output'],
			'data' => (isSet ($data) ? $data : NULL),
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
			'values'				=> array (),# Simple array of selectable values
			'title'					=> '',		# Introductory text
			'description'			=> '',		# Description text
			'output'				=> array (),# Presentation format
			'required'		=> 0,		# The minimum number which must be selected (defaults to 0)
#!# Hack!
			'maximum'		=> 999,		# The maximum number which must be selected (defaults to 0)
			'default'			=> array (),# Pre-selected item(s)
		);
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, __FUNCTION__);
		
		$arguments = $widget->getArguments ();
		
		# Register the element name to enable duplicate checking
		$this->registerElementName ($arguments['name']);
		
		# Obtain the value of the form submission (which may be empty)
		$widget->setValue (isSet ($this->form[$arguments['name']]) ? $this->form[$arguments['name']] : array ());
		/* #!# Is this the same as what it was? :
		# Make sure the element is not empty; NB the [] is required to prevent Uninitialized string offsets at the stickynessHtml creation point - basically the isSet would otherwise fail because of checking an array key existing for a non-array element
		if (!isSet ($this->form[$arguments['name']])) {$this->form[$arguments['name']][] = '';}
		*/
		
		$elementValue = $widget->getValue ();
		
		# Check that the array of values is not empty
		if (empty ($arguments['values'])) {$this->formSetupErrors['checkboxesNoValues'] = 'No values have been set for the set of checkboxes.';}
		
		# Check that the given minimum required is not more than the number of checkboxes actually available
		$totalSubItems = count ($arguments['values']);
		if ($arguments['required'] > $totalSubItems) {$this->formSetupErrors['checkboxesMinimumMismatch'] = "The required minimum number of checkboxes (<strong>{$arguments['required']}</strong>) specified is above the number of checkboxes actually available (<strong>$totalSubItems</strong>).";}
		
		# Check whether the array is an associative array
		$valuesAreAssociativeArray = application::isAssociativeArray ($arguments['values']);
		$submittableValues = ($valuesAreAssociativeArray ? array_keys ($arguments['values']) : array_values ($arguments['values']));
		
		# Ensure that all initial values are in the array of values
		$arguments['default'] = application::ensureArray ($arguments['default']);
		foreach ($arguments['default'] as $defaultValue) {
			if (!in_array ($defaultValue, $submittableValues)) {
				$missingValues[] = $defaultValue;
			}
		}
		if (isSet ($missingValues)) {
			$totalMissingValues = count ($missingValues);
			$this->formSetupErrors['defaultMissingFromValuesArray'] = "In the <strong>{$arguments['name']}</strong> element, the default " . ($totalMissingValues > 1 ? 'values ' : 'value ') . implode (', ', $missingValues) . ($totalMissingValues > 1 ? ' were' : ' was') . ' not found in the list of available items for selection by the user.';
		}
		
		# Start a tally to check the number of checkboxes checked
		$checkedTally = 0;
		
		# Loop through each element subname and construct HTML
		$widgetHtml = '';
		foreach ($arguments['values'] as $key => $value) {
			
			# Assign the submittable value
			$submittableValue = ($valuesAreAssociativeArray ? $key : $value);
			
			# Define the element ID, which must be unique	
			$elementId = $this->cleanId ("{$this->name}__{$arguments['name']}__{$submittableValue}");
			
			# Assign the initial value if the form is not posted (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid)
			if (!$this->formPosted) {
				if (in_array ($submittableValue, $arguments['default'])) {
					$elementValue[$submittableValue] = true;
				}
			}
			
			# Apply stickyness to each checkbox if necessary
			$stickynessHtml = '';
			if (isSet ($elementValue[$submittableValue])) {
				if ($elementValue[$submittableValue]) {
					$stickynessHtml = ' checked="checked"';
					
					# Tally the number of items checked
					$checkedTally++;
				}
			} else {
				# Ensure every element is defined (even if empty), so that the case of writing to a file doesn't go wrong
				$elementValue[$submittableValue] = '';
			}
			
			# Create the HTML; note that spaces (used to enable the 'label' attribute for accessibility reasons) in the ID will be replaced by an underscore (in order to remain valid XHTML)
			$widgetHtml .= "\n\t\t\t" . '<input type="checkbox" name="' . $this->name . "[{$arguments['name']}][{$submittableValue}]" . '" id="' . $elementId . '" value="true"' . $stickynessHtml . ' /><label for="' . $elementId . '">' . $value . "</label><br />";
		}
		
		# Make sure the number of checkboxes given is above the $arguments['required']
		if ($checkedTally < $arguments['required']) {
			$elementProblems['insufficientSelected'] = "A minimum of {$arguments['required']} checkboxes are required to be selected.";
		}
		
		# Make sure the number of checkboxes given is above the maximum required
		#!# Hacked in quickly on 041103 - needs regression testing
		if ($checkedTally > $arguments['maximum']) {
			$elementProblems['tooManySelected'] = "A maximum of {$arguments['maximum']} checkboxes are required to be selected.";
		}
		
		# Describe restrictions on the widget
		#!# Show maximum also
		if ($arguments['required'] > 1) {$restriction = "Minimum {$arguments['required']} items required";}
		
		# Re-assign back the value
		$this->form[$arguments['name']] = $elementValue;
		
		# Get the posted data
		if ($this->formPosted) {
			
			# For the component array, create an array with every defined element being assigned as itemName => boolean; checking is done against the available values rather than the posted values to prevent offsets
			foreach ($arguments['values'] as $key => $value) {
				$submittableValue = ($valuesAreAssociativeArray ? $key : $value);
				$data['rawcomponents'][$submittableValue] = ($this->form[$arguments['name']][$submittableValue] == 'true');
			}
			
			# Make an array of those items checked, starting with an empty array in case none are checked
			$checked = array ();
			$checkedPresented = array ();
			foreach ($data['rawcomponents'] as $key => $value) {
				if ($value) {
					$checked[] = $key;
					
					# For the presented version, substitute the index name with the presented name if the array is associative
					$checkedPresented[] = ($valuesAreAssociativeArray ? $arguments['values'][$key] : $key);
				}
			}
			
			# Separate the compiled/presented items by a comma-newline
			$data['compiled'] = implode (",\n", $checked);
			$data['presented'] = implode (",\n", $checkedPresented);
		}
		
		# Add the widget to the master array for eventual processing
		$this->elements[$arguments['name']] = array (
			'type' => __FUNCTION__,
			'html' => $widgetHtml,
			'title' => $arguments['title'],
			'description' => $arguments['description'],
			'restriction' => (isSet ($restriction) ? $restriction : false),
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => false,
			'requiredButEmpty' => false, # This is covered by $elementProblems
			'suitableAsEmailTarget' => false,
			'output' => $arguments['output'],
			'data' => (isSet ($data) ? $data : NULL),
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
			'title'					=> '',		# Introductory text
			'description'			=> '',		# Description text
			'output'				=> array (),# Presentation format
			'required'				=> false,	# Whether required or not
			'level'					=> 'date',	# Whether to show a 'datetime' or just 'date' widget set
			'default'				=> '',		# Initial value - either 'timestamp' or an SQL string
		);
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, __FUNCTION__);
		
		$arguments = $widget->getArguments ();
		
		# Register the element name to enable duplicate checking
		$this->registerElementName ($arguments['name']);
		
		# Obtain the value of the form submission (which may be empty)  (ensure that a full date and time array exists to prevent undefined offsets in case an incomplete set has been posted)
		$widget->setValue (isSet ($this->form[$arguments['name']]) ? $this->form[$arguments['name']] : array ('year' => '', 'month' => '', 'day' => '', 'time' => ''));
		
		$elementValue = $widget->getValue ();
		
		# Start a flag later used for checking whether all fields are empty against the requirement that a field should be completed
		$requiredButEmpty = false;
		
		# Load the date processing library
		require_once ('datetime.php');
		
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
				if (($this->autoCenturyConversionEnabled) && (strlen ($elementValue['year']) == 2)) {
					$elementValue['year'] = (($elementValue['year'] <= $this->autoCenturyConversionLastYear) ? '20' : '19') . $elementValue['year'];
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
							if ($this->autoCenturyConversionEnabled) {
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
						
						# If, instead, the time parsing fails, leave the original submitted version and add the problem to the errors array
						} else {
							$elementProblems['timePartInvalid'] = 'The time part is invalid!';
						}
					}
				}
			}
		}
		
		# Describe restrictions on the widget
		if ($arguments['level'] == 'datetime') {$restriction = 'Time can be entered flexibly';}
		
		# Start to define the widget's core HTML
		#!# Add fieldsets to remaining form widgets or scrap
		$widgetHtml = "\n\t\t\t<fieldset>";
		
		# Add in the time if required
		if ($arguments['level'] == 'datetime') {
			$widgetHtml .= "\n\t\t\t\t" . '<span class="' . (!isSet ($elementProblems['timePartInvalid']) ? 'comment' : 'warning') . '">t:&nbsp;</span><input name="' . $this->name . '[' . $arguments['name'] . '][time]" type="text" size="10" value="' . $elementValue['time'] . '" />';
		}
		
		# Define the date, month and year input boxes; if the day or year are 0 then nothing will be displayed
		$widgetHtml .= "\n\t\t\t\t" . '<span class="comment">d:&nbsp;</span><input name="' . $this->name . '[' . $arguments['name'] . '][day]"  size="2" maxlength="2" value="' . (($elementValue['day'] != '00') ? $elementValue['day'] : '') . '" />&nbsp;';
		$widgetHtml .= "\n\t\t\t\t" . '<span class="comment">m:</span>';
		$widgetHtml .= "\n\t\t\t\t" . '<select name="' . $this->name . '[' . $arguments['name'] . '][month]">';
		$months = array (1 => 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'June', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
		$widgetHtml .= "\n\t\t\t\t\t" . '<option value="">Select</option>';
		foreach ($months as $monthNumber => $monthName) {
			$widgetHtml .= "\n\t\t\t\t\t" . '<option value="' . sprintf ('%02s', $monthNumber) . '"' . (($elementValue['month'] == sprintf ('%02s', $monthNumber)) ? ' selected="selected"' : '') . '>' . $monthName . '</option>';
		}
		$widgetHtml .= "\n\t\t\t\t" . '</select>';
		$widgetHtml .= "\n\t\t\t\t" . '<span class="comment">y:&nbsp;</span><input size="4" name="' . $this->name . '[' . $arguments['name'] . '][year]" maxlength="4" value="' . (($elementValue['year'] != '0000') ? $elementValue['year'] : '') . '" />' . "\n\t\t";
		$widgetHtml .= "\n\t\t\t</fieldset>";
		
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
				$data['presented'] = (($arguments['level'] == 'datetime') ? $this->form[$arguments['name']]['time'] . ', ': '') . date ('jS F, Y', mktime (0, 0, 0, $this->form[$arguments['name']]['month'], $this->form[$arguments['name']]['day'], $this->form[$arguments['name']]['year']));
			}
		}
		
		# Add the widget to the master array for eventual processing
		$this->elements[$arguments['name']] = array (
			'type' => __FUNCTION__,
			'html' => $widgetHtml,
			'title' => $arguments['title'],
			'description' => $arguments['description'],
			'restriction' => (isSet ($restriction) ? $restriction : false),
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $arguments['required'],
			'requiredButEmpty' => $requiredButEmpty,
			'suitableAsEmailTarget' => false,
			'output' => $arguments['output'],
			'data' => (isSet ($data) ? $data : NULL),
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
			'uploadDirectory'		=> NULL,	# Path to the file; any format acceptable
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
		
		# Register the element name to enable duplicate checking
		$this->registerElementName ($arguments['name']);
		
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
		
		# Check that the selected directory exists and is writable (it need not exist at present, merely be writable)
		if (!application::directoryIsWritable ($arguments['uploadDirectory'])) {
			$this->formSetupErrors['directoryNotWritable'] = "The directory specified for the <strong>{$arguments['name']}</strong> upload element is not writable. Please check that the file permissions to ensure that the webserver 'user' can write to the directory.";
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
			$widgetHtml .= '<input name="' . $this->name . "[{$arguments['name']}][{$subfield}]\" type=\"file\" size=\"{$arguments['size']}\" />";
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
			'name'			=> '',				# Name of the element (Optional)
			'values'				=> array (),		# Associative array of selectable values
			'output'				=> array (),		# Presentation format
			'title'					=> 'Hidden data',	# Title (CURRENTLY UNDOCUMENTED)
		);
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, __FUNCTION__);
		
		$arguments = $widget->getArguments ();
		
		# Register the element name to enable duplicate checking
		$this->registerElementName ($arguments['name']);
		
		# Flag that a hidden element is present
		$this->hiddenElementPresent = true;
		
		# Ensure the elementName is not empty
		if (!$arguments['name']) {$arguments['name'] = 'hidden';}
		
		# Check that the values array is actually an array
		if (!is_array ($arguments['values'])) {$this->formSetupErrors['hiddenElementNotArray'] = "The hidden data specified for the <strong>{$arguments['name']}</strong> hidden input element must be an array but is not currently.";}
		
		#!# Need to add a check for a non-empty array of values
		
		# Loop through each hidden data sub-array and create the HTML
		$widgetHtml = "\n";
		foreach ($arguments['values'] as $key => $value) {
			$widgetHtml .= "\n\t" . '<input type="hidden" name="' . $this->name . "[{$arguments['name']}][$key]" . '" value="' . $value . '" />';
		}
		$widgetHtml .= "\n";
		
		# Get the posted data
		if ($this->formPosted) {
			
			# Map the components onto the array directly and assign the compiled version; no attempt is made to combine the data
			$data['rawcomponents'] = $this->form[$arguments['name']];
			
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
			'problems' => false, #!# Should ideally be getElementProblems but can't create an object as no real paramaters to supply
			'required' => false,
			'requiredButEmpty' => false,
			'suitableAsEmailTarget' => false,
			'output' => array (),	// The output specification must always be array
			'data' => (isSet ($data) ? $data : NULL),
		);
	}
	
	
	# Function to clean an HTML id attribute
	function cleanId ($id)
	{
		# Define the replacements
		$replacements = array (' ', '!', '(', ')', '[', ']',);
		
		# Perform the replacements
		$id = str_replace ($replacements, '_', $id);
		
		# Return the cleaned ID
		return $id;
	}
	
	
	# Function to determine whether an array of values for a select form is suitable as an e-mail target
	function suitableAsEmailTarget ($values)
	{
		# Ensure the values are an array
		$values = application::ensureArray ($values);
		
		# Return true if all e-mails are valid
		$allValidEmail = true;
		foreach ($values as $value) {
			if (!application::validEmail ($value)) {
				$allValidEmail = false;
				break;
			}
		}
		if ($allValidEmail) {return true;}
		
		# If any of the suffixed ones would not be valid as an e-mail, then flag 'syntax'
		foreach ($values as $value) {
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
		foreach ($_FILES[$this->name]['name'] as $name => $subElements) {
			
			# Loop through each upload widget set's subelements (e.g. 4 items if there are 4 input tags within the widget set)
			foreach ($subElements as $key => $value) {
				
				# Map the file information into the main form element array
				if (!empty ($value)) {
					$this->form[$name][$key] = array (
						'name' => $_FILES[$this->name]['name'][$name][$key],
						'type' => $_FILES[$this->name]['type'][$name][$key],
						'tmp_name' => $_FILES[$this->name]['tmp_name'][$name][$key],
						#'error' => $_FILES[$this->name]['error'][$name][$key],
						'size' => $_FILES[$this->name]['size'][$name][$key],
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
	function setOutputConfirmationEmail ($chosenelementName, $administrator = '', $includeAbuseNotice = true, $subjectTitle = 'Form submission results')
	{
		# Flag that this method is required
		$this->outputMethods['confirmationEmail'] = true;
		
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
		} else {
			if (!is_writable ($filename)) {
				$this->formSetupErrors['resultsFileNotWritable'] = 'The specified (but already existing) results file is not writable; please check its permissions.';
			}
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
			    'name'	=> 'security-verifications',
				'values'	=> $securityFields,
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
		# Open the surrounding <div> if relevant
		if ($this->div) {echo "\n\n<div class=\"{$this->div}\">";}
		
		# Show the presentation matrix if required (this is allowed to bypass the form setup so that the administrator can see what corrections are needed)
		if ($this->displayPresentationMatrix) {$this->displayPresentationMatrix ();}
		
		# Check if the form and PHP environment has been set up OK
		if (!$this->formSetupOk ()) {return false;}
		
		# Show debugging information firstly if required
		if ($this->debug) {$this->showDebuggingInformation ();}
		
		# Check whether the user is a valid user (must be before the formSetupOk check)
		if (!$this->validUser ()) {return false;}
		
		# Check whether the facility is open
		if (!$this->facilityIsOpen ()) {return false;}
		
		# Validate hidden security fields
		if ($this->hiddenSecurityFieldSubmissionInvalid ()) {return false;}
		
		# If the form is not posted or contains problems, display it and flag that it has been displayed
		if (!$this->formPosted || $this->getElementProblems ()) {
			echo $this->constructFormHtml ($this->elements, $this->elementProblems);
			if ($this->div) {echo "\n</div>";}
			return false;
		}
		
		# Process any form uploads
		$this->doUploads ();
		
		# Prepare the data
		$this->outputData = $this->prepareData ();
		
		# If required, display a summary confirmation of the result
		if ($this->displayFormCompleteText) {echo "\n" . '<p class="completion">' . $this->formCompleteText . ' </p>';}
		
		# Loop through each of the processing methods and output it based on the requested method
		foreach ($this->outputMethods as $outputType => $required) {
			$this->outputData ($outputType);
		}
		
		# If required, display a link to reset the page
		if ($this->displayFormCompleteText) {echo "\n" . '<p><a href="' . $_SERVER['REQUEST_URI'] . '">Click here to reset the page.</a></p>';}
		
		# Close the surrounding <div> if relevant
		if ($this->div) {echo "\n\n</div>";}
		
		# Return the data
		return $this->outputData ('processing');
	}
	
	
	## Form processing support ##
	
	# Function to determine whether this facility is open
	function facilityIsOpen ()
	{
		# Check that the opening time has passed, if one is specified, ensuring that the date is correctly specified
		if ($this->opening) {
			if (time () < strtotime ($this->opening . ' GMT')) {
				echo '<p class="warning">This facility is not yet open. Please return later.</p>';
				return false;
			}
		}
		
		# Check that the closing time has passed
		if ($this->closing) {
			if (time () > strtotime ($this->closing . ' GMT')) {
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
		
		# Check that an empty form hasn't been requested (i.e. there must be at least one form field)
		#!# This needs to be modified to take account of headers (which should not be included)
		if (empty ($this->elements)) {$this->formSetupErrors['formEmpty'] = 'No form elements have been defined (i.e. the form is empty).';}
		
		# If there are any duplicated keys, list each duplicated key in bold with a comma between (but not after) each
		if (!empty ($this->duplicatedelementNames)) {$this->formSetupErrors['duplicatedelementNames'] = 'The following field ' . (count (array_unique ($this->duplicatedelementNames)) == 1 ? 'name has' : 'names have been') . ' been duplicated in the form setup: <strong>' . implode ('</strong>, <strong>', array_unique ($this->duplicatedelementNames)) .  '</strong>.';}
		
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
		if ($this->display != 'template') {return;}
		
		# Ensure the template pattern includes the placemarker %element
		$placemarker = '%element';
		$checkParameters = array ('displayTemplatePatternWidget', 'displayTemplatePatternLabel', 'displayTemplatePatternSpecial');
		foreach ($checkParameters as $checkParameter) {
			if (strpos ($this->$checkParameter, $placemarker) === false) {
				$this->formSetupErrors["{$checkParameter}Invalid"] = "The <tt>{$checkParameter}</tt> parameter must include the placemarker <tt>{$placemarker}</tt> ; by default the parameter's value is <tt>{$this->argumentDefaults[$checkParameter]}</tt>";
			}
		}
		
		# Check that none of the $checkParameters items are the same
		foreach ($checkParameters as $checkParameter) {
			$values[] = $this->$checkParameter;
		}
		if (count ($values) != count (array_unique ($values))) {
			$this->formSetupErrors['displayTemplatePatternDuplication'] = 'The values of the parameters <tt>' . implode ('</tt>, <tt>', $checkParameters) . '</tt> must all be unique.';
		}
		
		# Attempt to read the template; if not, do not perform further checks
		if (is_file ($this->displayTemplate)) {
			#!# Add an is_readable check here or throw error if not
			if (!$this->displayTemplateContents = @file_get_contents ($this->displayTemplate)) {
				$this->formSetupErrors['templateNotFound'] = 'You appear to have specified a template file for the <tt>displayTemplate</tt> parameter, but the file could not be opened.</tt>';
				return false;
			}
		} else {
			$this->displayTemplateContents = $this->displayTemplate;
		}
		
		# Assemble the list of elements and their replacements
		$elements = array_keys ($this->elements);
		$this->displayTemplateElementReplacements = array ();
		foreach ($elements as $element) {
			$this->displayTemplateElementReplacements[$element]['widget'] = str_replace ($placemarker, $element, $this->displayTemplatePatternWidget);
			$this->displayTemplateElementReplacements[$element]['label'] = str_replace ($placemarker, $element, $this->displayTemplatePatternLabel);
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
			'RESET' => $this->resetButton,	// Placemarker for the reset button - if there is one
			'REQUIRED' => false,			// Placemarker for the required fields indicator text
		);
		
		# Loop through each special, allocating its replacement shortcut and checking it exists if necessary
		foreach ($specials as $special => $required) {
			$this->displayTemplateElementReplacementsSpecials[$special] = str_replace ($placemarker, $special, $this->displayTemplatePatternSpecial);
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
		if (!$this->developmentEnvironment) {
			if ((bool) ini_get ('display_errors')) {$this->formSetupErrors['environmentDisplayErrors'] = 'The PHP configuration setting display_errors must be set to <strong>false</strong>.';}
		}
		
		# Check that magic_quotes are switched off; escaping of user input is handled manually
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
		if (isSet ($this->elements[$name])) {$this->duplicatedelementNames[] = $name;}
	}
	
	
	/**
	 * Function to check for namespace clashes against internal defaults
	 * @todo Ideally replace each clashable item with an encoding method somehow or ideally eradicate the restrictions
	 * @access private
	 */
	function preventNamespaceClashes ()
	{
		# Disallow [ or ] in a form name
		if ((strpos ($this->name, '[') !== false) || (strpos ($this->name, ']') !== false)) {
			$this->formSetupErrors['namespaceFormNameContainsSquareBrackets'] = 'The name of the form ('. $this->name . ') cannot include square brackets.';
		}
		
		#!# Need a check to disallow valid e-mail addresses as an element name, or encode - this is to prevent setOutputEmail () picking a form element which should actually be an e-mail address
		
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
		
		#!# Convert this to returning an array which gets merged with the formSetupErrors array
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
		if ($this->display == 'template') {
			$html = '';
			$this->displayTemplateContents = str_replace ($this->displayTemplateElementReplacementsSpecials['PROBLEMS'], $this->problemsList ($problems), $this->displayTemplateContents);
		} else {
			$html  = "\n" . $this->problemsList ($problems);
		}
		
		# Add the required field indicator display message if required
		if (($this->display != 'template') && ($this->requiredFieldIndicator === 'top')) {$html .= $requiredFieldIndicatorHtml;}
		
		# Start the constructed form HTML
		$html .= "\n" . '<form method="post" action="' . $this->submitTo . '" enctype="' . ($this->uploadProperties ? 'multipart/form-data' : 'application/x-www-form-urlencoded') . '">';
		
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
				if ($this->display == 'template') {
					$this->displayTemplateContents = str_replace ($this->displayTemplateElementReplacements[$name], '', $this->displayTemplateContents);
					$formHtml = $this->displayTemplateContents;
				}
				*/
				continue;
			}
			
			# If colons are set to show, add them
			if ($this->displayColons) {$elementAttributes['title'] .= ':';}
			
			# If the element is required, and indicators are in use add an indicator
			if ($this->requiredFieldIndicator && $elementAttributes['required']) {
				$elementAttributes['title'] .= '&nbsp;*';
			}
			
			# If the form has been posted AND the element has any problems or is empty, add the warning CSS class
			if ($this->formPosted && (($elementAttributes['problems']) || ($elementAttributes['requiredButEmpty']))) {
				$elementAttributes['title'] = '<span class="warning">' . $elementAttributes['title'] . '</span>';
			}
			
			# Select whether to show restriction guidelines
			$displayRestriction = ($this->displayRestrictions && $elementAttributes['restriction']);
			
			# Clean the ID
			#!# Move this into the element attributes set at a per-element level, for consistency so that <label> is correct
			$id = $this->cleanId ($name);
			
			# Display the display text (in the required format), unless it's a hidden array (i.e. no elementText to appear)
			switch ($this->display) {
				
				# Display as paragraphs
				case 'paragraphs':
					if ($elementAttributes['type'] == 'heading') {
						$formHtml .= "\n" . $elementAttributes['html'];
					} else {
						$formHtml .= "\n" . '<p id="' . $id . '">';
						$formHtml .= "\n\t";
						if ($this->displayTitles) {
							$formHtml .= $elementAttributes['title'] . '<br />';
							if ($displayRestriction) {$formHtml .= "<br /><span class=\"restriction\">(" . ereg_replace ("\n", '<br />', $elementAttributes['restriction']) . ')</span>';}
						}
						$formHtml .= $elementAttributes['html'];
						if ($this->displayDescriptions) {if ($elementAttributes['description']) {$formHtml .= "<br />\n<span class=\"description\">" . $elementAttributes['description'] . '</span>';}}
						$formHtml .= "\n</p>";
					}
					break;
					
				# Display using divs for CSS layout mode; this is different to paragraphs as the form fields are not conceptually paragraphs
				case 'css':
					$formHtml .= "\n" . '<div class="row" id="' . $id . '">';
					if ($elementAttributes['type'] == 'heading') {
						$formHtml .= "\n\t<span class=\"title\">" . $elementAttributes['html'] . '</span>';
					} else {
						$formHtml .= "\n\t";
						if ($this->displayTitles) {
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
						if ($this->displayDescriptions) {if ($elementAttributes['description']) {$formHtml .= "\n\t<span class=\"description\">" . $elementAttributes['description'] . '</span>';}}
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
					$formHtml .= "\n\t" . '<tr class="' . $id . '">';
					if ($elementAttributes['type'] == 'heading') {
						# Start by determining the number of columns which will be needed for headings involving a colspan
						$colspan = 1 + ($this->displayTitles) + ($this->displayDescriptions);
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
		
		# In the table mode, having compiled all the elements surround the elements with the table tag
		if ($this->display == 'tables') {$formHtml = "\n\n" . '<table summary="Online submission form">' . $formHtml . "\n</table>";}
		
		# Add in any hidden HTML, between the </table> and </form> tags (this also works for the template, where it is stuck on afterwards
		$formHtml .= $hiddenHtml;
		
		# Add the form button, either at the start or end as required
		#!# submit_x and submit_y should be treated as a reserved word when using submitButtonAccesskey (i.e. generating type="image")
		$submitButtonText = $this->submitButtonText . (!empty ($this->submitButtonAccesskey) ? '&nbsp; &nbsp;[Alt+' . $this->submitButtonAccesskey . ']' : '');
		$formButtonHtml = '<input value="' . $submitButtonText . '" ' . (!empty ($this->submitButtonAccesskey) ? "accesskey=\"{$this->submitButtonAccesskey}\" "  : '') . 'type="' . (!$this->submitButtonImage ? 'submit' : "image\" src=\"{$this->submitButtonImage}\" name=\"submit\" alt=\"{$submitButtonText}") . '" class="button" />';
		if ($this->display == 'template') {
			$formHtml = str_replace ($this->displayTemplateElementReplacementsSpecials['SUBMIT'], $formButtonHtml, $formHtml);
		} else {
			$formButtonHtml = "\n\n" . '<p class="submit">' . $formButtonHtml . '</p>';
			$formHtml = ((!$this->submitButtonAtEnd) ? ($formButtonHtml . $formHtml) : ($formHtml . $formButtonHtml));
		}
		
		# Add in the required field indicator for the template version
		if (($this->display == 'template') && ($this->requiredFieldIndicator)) {
			$formHtml = str_replace ($this->displayTemplateElementReplacementsSpecials['REQUIRED'], $requiredFieldIndicatorHtml, $formHtml);
		}
		
		# Add in a reset button if wanted
		if ($this->resetButton) {
			$resetButtonHtml = '<input value="' . $this->resetButtonText . (!empty ($this->resetButtonAccesskey) ? '&nbsp; &nbsp;[Alt+' . $this->resetButtonAccesskey . ']" accesskey="' . $this->resetButtonAccesskey : '') . '" type="reset" class="resetbutton" />';
			if ($this->display == 'template') {
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
		if (($this->display != 'template') && ($this->requiredFieldIndicator === 'bottom') || ($this->requiredFieldIndicator === true)) {$html .= $requiredFieldIndicatorHtml;}
		
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
		return $html = (($this->formPosted && $problemsList) ? application::showUserErrors ($problemsList, $parentTabLevel = 0, (count ($problemsList) > 1 ? 'Various problems were' : 'A problem was') . ' found with the form information you submitted, as detailed below; please make the necessary corrections and re-submit the form:') : '');
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
			
			'richtext' => array (
				'_descriptions' => array (
					'presented'		=> 'Show as unaltered string, with HTML code visible',
				),
				'file'				=> array ('presented'),
				'email'				=> array ('presented'),
				'confirmationEmail'	=> array ('presented'),
				'screen'			=> array ('presented'),
				'processing'		=> array ('presented'),
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
		
		# Introduce the table
		$html .= "\n\n" . '<p class="success">The information submitted is confirmed as:</p>';
		$html .= "\n" . '<table class="results" summary="Table of results">';
		
		# Assemble the HTML, convert newlines to breaks (without a newline in the HTML), tabs to four spaces, and convert HTML entities
		foreach ($presentedData as $name => $data) {
			
			# Remove empty elements from display
			if (empty ($data)) {continue;}
			
			/*
			# For associative select types, substitute the submitted value with the the visible value
			#!# PATCHED IN 041201; This needs to be applied to other select types and to dealt with generically in the processing stage; also, should this be made configurable, or is it assumed that the visible version is always wanted for the confirmation screen?
			if ($this->elements[$name]['type'] == 'select') {
				if (application::isAssociativeArray ($this->elements[$name]['values'])) {
					foreach ($this->form[$name] as $key => $value) {
						$data[$key] = $this->form[$name]['values'][$data[$key]];
					}
				}
			}
			*/
			
			# If the data is an array, convert the data to a printable representation of the array
			if (is_array ($data)) {$data = application::printArray ($data);}
			
			# Compile the HTML
			$html .= "\n\t<tr>";
			$html .= "\n\t\t" . '<td class="key">' . (isSet ($this->elements[$name]['title']) ? $this->elements[$name]['title'] : $name) . ':</td>';
			$html .= "\n\t\t" . '<td class="value">' . str_replace (array ("\n", "\t"), array ('<br />', str_repeat ('&nbsp;', 4)), htmlentities ($data)) . '</td>';
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
	 	$this->outputDataEmailTypes ($presentedData, 'email');
	 }
	 
	 
	/**
	 * Wrapper function to output the data via e-mail
	 * @access private
	 */
	 function outputDataConfirmationEmail ($presentedData)
	 {
	 	# Pass through
	 	$this->outputDataEmailTypes ($presentedData, 'confirmationEmail');
	 }
	 
	 
	/**
	 * Function to output the data via e-mail for either e-mail type
	 * @access private
	 */
	function outputDataEmailTypes ($presentedData, $outputType)
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
			
			# Assemble a master array of e-mail text, adding the real element name if it's the result rather than confirmation e-mail type. NB: this used to be using str_pad in order to right-align the names, but it doesn't look all that neat in practice: str_pad ($this->elements[$name]['title'], ($this->longestKeyNameLength ($this->outputData) + 1), ' ', STR_PAD_LEFT) . ': ' . $presentedData
			foreach ($presentedData as $name => $data) {
				
				# Remove empty elements from display
				#!# Make this a hook
				if (empty ($data)) {continue;}
				
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
			echo "\n\n" . '<p class="' . ($success ? 'success' : 'error') . '">' . ($success ? 'A confirmation e-mail has been sent' : 'There was a problem sending a confirmation e-mail') . ' to the address you gave (' . 					$presentedData[$name] = str_replace ('@', '<span>&#64;</span>', htmlentities ($this->configureResultConfirmationEmailRecipient)) . ').</p>';
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
					$attributes['name'] = $_FILES[$this->name]['name'][$name][$key] = $arguments['forcedFileName'];
				}
				
				# Check whether a file already exists
				if (file_exists ($existingFileName = ($arguments['uploadDirectory'] . $_FILES[$this->name]['name'][$name][$key]))) {
					
					# Check whether the file being uploaded has the same checksum as the existing file
					if (md5_file ($existingFileName) != md5_file ($_FILES[$this->name]['tmp_name'][$name][$key])) {
						
						# If version control is enabled, move the old file, appending the date; if the file really cannot be renamed, append the date to the new file instead
						if ($arguments['enableVersionControl']) {
							$timestamp = date ('Ymd-Hms');
							if (!@rename ($existingFileName, $existingFileName . '.replaced-' . $timestamp)) {
								$_FILES[$this->name]['name'][$name][$key] .= '.forRenamingBecauseCannotMoveOld-' . $timestamp;
							}
							
						/* # If version control is not enabled, give a new name to the new file to prevent the old one being overwritten accidentally
						} else {
							# If a file of the same name but a different checksum exists, append the date and time to the proposed filename
							$_FILES[$this->name]['name'][$name][$key] .= date ('.Ymd-Hms');
							*/
						}
					}
				}
				
				# Attempt to upload the file
				if (!move_uploaded_file ($_FILES[$this->name]['tmp_name'][$name][$key], $arguments['uploadDirectory'] . $_FILES[$this->name]['name'][$name][$key])) {
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
					$data['compiled'][] = $arguments['uploadDirectory'] . $success;
				}
				
				# For the compiled version, give the number of files uploaded and their names
				$totalSuccesses = count ($successes);
				$data['presented'] .= $totalSuccesses . ($totalSuccesses > 1 ? ' files' : ' file') . ' (' . implode (', ', $filenames) . ') ' . ($totalSuccesses > 1 ? 'were' : 'was') . ' successfully copied over.';
			}
			
			# If there were any failures, list them also
			if ($failures) {
				$totalFailures = count ($failures);
				#!# ' ' being added even if there are no successes
				$data['presented'] .= ' ' . $totalFailures . ($totalFailures > 1 ? ' files' : ' file') . ' (' . implode (', ', $failures) . ') unfortunately failed to copy over for some unspecified reason.';
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
	
	# Temporary API compatibility fixes
	var $apiFix = array (
		// Widget parameters:
		'elementName' => 'name',
		'elementDescription' => 'description',
		'initialValue' => 'default',
		'initialValues' => 'default',
		'columns' => 'cols',
		'outputFormat' => 'output',
		'valuesArray' => 'values',
		'visibleSize' => 'size',
		'minimumRequired' => 'required',
		'maximumRequired' => 'maximum',
	);
	
	
	# Constructor
	function formWidget (&$form, $suppliedArguments, $argumentDefaults, $functionName, $subargument = NULL) {
		
		# Inherit the settings
		$this->settings =& $form->settings;
		
		# Assign the function name
		$this->functionName = $functionName;
		
		# Assign the arguments
		$this->arguments = $this->assignArguments ($suppliedArguments, $argumentDefaults, $functionName, $subargument);
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
	
	
	# Function to return the widget's problems
	function getElementProblems ($problems) {
		
		#!# Temporary: merge in any problems from the object
		if ($problems) {$this->elementProblems += $problems;}
		
		return $this->elementProblems;
	}
	
	
	# Function to merge the arguments
	function assignArguments ($suppliedArguments, $argumentDefaults, $functionName, $subargument = NULL)
	{
		# Apply API argument backwards compatibility
		$suppliedArguments = $this->apiFix ($suppliedArguments);
		
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
	
	
	# Function to fix arguments under the old API
	function apiFix ($arguments)
	{
		# Loop through the compatibility fixes
		foreach ($this->apiFix as $old => $new) {
			
			# Replace the old argument with the new if found
			if (isSet ($arguments[$old])) {
				$arguments[$new] = $arguments[$old];
				unset ($arguments[$old]);
			}
		}
		
		# Return the fixed arguments
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
	function regexpCheck ()
	{
		# End if the form is empty
		if (!$this->value) {return;}
		
		# Regexp checks (for non-e-mail types)
		if ($this->arguments['regexp'] && ($this->functionName != 'email')) {
			if (!ereg ($this->arguments['regexp'], $this->value)) {
				$this->elementProblems['failsRegexp'] = 'The submitted information did not match a specific pattern required for this section.';
			}
		}
		
		# E-mail check (for e-mail type)
		if ($this->functionName == 'email') {
			if (!application::validEmail ($this->value)) {
				$this->elementProblems['invalidEmail'] = 'The e-mail address you gave appears to be invalid.';
			}
		}
	}
	
	
	# Function to determine if a widget is required but empty
	function requiredButEmpty ()
	{
		return (($this->arguments['required']) && (!$this->value));
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
#!# Add links to the id="$name" form elements in cases of USER errors (not for the templating mode though)
#!# Need to prevent the form code itself being overwritable by uploads... (is that possible to ensure?)
#!# Add POST security for hidden fields - do this by ignoring the posted data (submitting to external can't be dealt with)
#!# Not all $widgetHtml declarations have an id="" given (make sure it is $this>cleanId'd though)
#!# Add <label> and (where appropriate) <fieldset> support throughout - see also http://www.aplus.co.yu/css/styling-form-fields/ ; http://www.bobbyvandersluis.com/articles/formlayout.php ; http://www.simplebits.com/notebook/2003/09/16/simplequiz_part_vi_formatting.html ; http://www.htmldog.com/guides/htmladvanced/forms/


# Version 2 feature proposals
#!# Full object orientation - change the form into a package of objects
#!#		Change each input type to an object, with a series of possible checks that can be implemented - class within a class?
#!# 	Change the output methods to objects
#!# Allow multiple carry-throughs, perhaps using formCarried[$formNumber][...]: Add carry-through as an additional array section; then translate the additional array as a this-> input to hidden fields.
#!# Enable javascript as an option
#!# 	Use ideas in http://www.sitepoint.com/article/1273/3 for having js-validation with an icon
#!# 	Style like in http://www.sitepoint.com/examples/simpletricks/form-demo.html [linked from http://www.sitepoint.com/article/1273/3]
#!# Add AJAX validation flag See: http://particletree.com/features/smart-validation-with-ajax (but modified version needed because this doesn't use Unobtrusive DHTML)
#!# Self-creating


?>