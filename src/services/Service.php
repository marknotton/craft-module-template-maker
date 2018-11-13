<?php

/**
 * Methods for creating dynamic templates
 */

namespace modules\helpers\services;
use modules\helpers\Helpers;

use Craft;
use craft\web\View;
use craft\base\Component;
use craft\helpers\StringHelper;
use craft\helpers\Template;
use craft\elements\Entry;

class TemplateMaker extends Component {

  private $sections;
  private $segments;
  private $section;
  private $entryType;
  private $entryTypes;
  private $sectionSettings;
  private $allFields;
  private $triggered = false;
  private $rules = [];
  private $variables = "";
  private $tabLength = 2;

  // Exclude these tabs from being generated.
  private $tabExclusions = ['seo'];

  // Elements Tags that are valid markup and don't need to be validated.
  private $elementExceptions = ['main', 'nav', 'aside', 'header', 'footer', 'article', 'section'];

  // Matching tab names should be rendered in their own block types of they exist.
  private $blocks = ['navigation', 'header', 'main', 'content', 'aside', 'footer'];

  // Field Aliases
  // If field has a specific handle, refer to sample file by reference
  private $fieldAliases = [
    'featuredImage' => 'featured-image',
    'contentBlocks' => 'content-blocks',
    'telephone'     => 'telephone'
  ];

  // Field Aliases To Include
  // If one of the above $fieldAliases is found, clone the file and set an include
  // within the template markup. Otherwise, just include it's content.
  private $fieldAliasesToInclude = [
    'featured-image',
    'content-blocks'
  ];

  // Assign a field type to a file.
  private $fieldFiles = [
    // Craft CMS
    'craft\fields\Assets'       => 'Assets',
    'craft\fields\Matrix'       => 'Matrix',
    'craft\fields\PlainText'    => 'PlainText',
    'craft\fields\Categories'   => 'Categories',
    'craft\fields\Checkboxes'   => 'Checkboxes',
    'craft\fields\Color'        => 'Color',
    'craft\fields\Date'         => 'Date',
    'craft\fields\Dropdown'     => 'Dropdown',
    'craft\fields\Email'        => 'Email',
    'craft\fields\Lightswitch'  => 'Lightswitch',
    'craft\fields\MultiSelect'  => 'MultiSelect',
    'craft\fields\Number'       => 'Number',
    'craft\fields\Entries'      => 'Entries',
    'craft\fields\RadioButtons' => 'RadioButtons',
    'craft\fields\Table'        => 'Table',
    'craft\fields\Tags'         => 'Tags',
    'craft\fields\Url'          => 'Url',
    'craft\fields\Users'        => 'Users',
    'craft\redactor\Field'      => 'Redactor',
    // Third Party
    'modules\helpers\fields\Video' => 'Video',
    'verbb\supertable\fields\SuperTableField' => 'SuperTable',
    'supercool\tablemaker\fields\TableMakerField' => 'TableMaker'
  ];

  // ===========================================================================
  // Init
  // ===========================================================================

  public function init() {

    if ( $this->triggered == false ) {

      $this->triggered = true;

      // Globally define the URL segments
      $this->segments = Craft::$app->getRequest()->getSegments();

      // Checks in the URL to determine if the user is actually on an individual entry type page
      if ( in_array("entrytypes", $this->segments) && array_search("entrytypes", $this->segments) < count($this->segments) - 1 ) {

        // Grab the section ID from the URL. It's the third from the end segment item.
        $sectionId = $this->segments[array_search('entrytypes', $this->segments) - 1];

        // Get the entryType ID from the last parameter in the URL
        $entryTypeId = $this->segments[array_search('entrytypes', $this->segments) + 1];

        // Set the Section and Entry type data
        $this->setSectionAndEntrytype($sectionId, $entryTypeId);

        // Set the path name and and template name.
        $path = $this->pathSanitiser();
        $templateName = $this->templateSantiser();

        // Set a timestamp to be used as a filename suffix should there be a naming conflict.
        $timestamp = time();

        // Get a list of all the files that exist in the templates directory.
        // This will be tested against to instruct users if they are about to
        // overwrite a existing file.
        $allFiles = json_encode(Helpers::$app->request->getFileDirectory(null)) ?? [];

        // It's unlikely the path name and template name will ever be the same.
        // If this occures, force the template name to '_entry'
        if ( $path == $templateName ) {
          $templateName = '_entry';
        }

        // Define a bunch of data that will passed into the template maker form
        $settings = [
          'sectionId'   => $sectionId,
          'entryTypeId' => $entryTypeId,
          'path'        => $path,
          'template'    => $templateName,
          'timestamp'   => $timestamp,
          'allFiles'    => $allFiles,
        ];

        // Render the template-maker form and return the markup.
        $template = Craft::$app->view->renderTemplate("helpers/_template-maker/form", $settings);

        // Render the templateMakerForm variable as a JS variable.
        Craft::$app->view->registerJsVar('templateMakerForm', str_replace(array("\n", "\r"), '', $template));

        // $settings['timestamp'] = '';
        // $settings['variables'] = true;
        // $this->create($settings);
      }
    }
  }

  // ===========================================================================
  // Create Template
  // ===========================================================================

  public function create($data) {

    // Extract the settings array into variables
    extract((array)$data);

    // Path and files names ====================================================

    $path = !empty($path) ? rtrim('/'.$path, '/') : '';
    $template = $template.$timestamp.'.twig';
    $templatePath = Craft::getAlias('@templates').$path.'/'.$template;
    $this->variables = "";

    // Set the Section and Entry type data
    if (empty($this->section) || empty($this->entryType)) {

      $this->setSectionAndEntrytype($sectionId, $entryTypeId);

    }

    // Get field & tab data ====================================================

    // Get all field type data
    $this->allFields = Helpers::$app->query->fields();

    // Get all tabs for this entry type
    $allTabs = $this->entryType->getFieldLayout()->getTabs();

    $tabData = [];

    // Loop all fields within all tabs and create a clean array of useful data.
    foreach ($allTabs as $tab) {
      $fields = $tab->getFields();
      foreach ($fields as $field) {
        $fieldData = $this->allFields[array_search($field->id, array_column($this->allFields, 'id'))];
        $tabData[$tab->name][] = [
          'name'     => $field->name   ?? false,
          'handle'   => $field->handle ?? false,
          'id'       => $field->id     ?? false,
          'type'     => $fieldData['type'] ?? false,
          'settings' => $fieldData['settings'] ?? false
        ];
      }
    }

    // Create Paths ============================================================

    // If path is a directory, recursively generate the folder structure.
    if (!empty($path) && !is_dir(Craft::getAlias('@templates').'/'.$path)) {
      mkdir(Craft::getAlias('@templates').'/'.$path, 0777, true);
    }

    // Open a new file.
    $newTemplate = fopen($templatePath, 'w') or die('Cannot open file:  '.$templatePath);

    // Templating Markup =======================================================

    $markup = $this->commentHeader($this->entryType->name, '#'.$this->entryType->handle, 0, "/");

    $markup .= "\n{% extends '_layouts/main' %}\n\n";

    $markup .= "{% block content %}\n";

    // $content = $this->getFieldData($tabData);

    if ( $variables ?? false ) {

      $content = $this->getFieldData($tabData, 'variables');
      $markup .= "\n".$this->variables;

    } else {

      $content = $this->getFieldData($tabData);

    }

    $markup .= $content;

    $markup .= "\n{% endblock %}";

    // Write file ==============================================================

    fwrite($newTemplate, $markup);

    // Data to pass back to the user ===========================================

    return [
      'path'               => ltrim($path, '/'),
      'template'           => $template,
      'templateSystemPath' => $templatePath,
      'templatePath'       => ltrim(str_replace(Craft::getAlias('@templates'), '', $templatePath), '/'),
      'newTimestamp'       => time()
    ];

  }

  // ===========================================================================
  // Get Field Type Data
  // ===========================================================================

  private function getFieldData($tabs, $rules = null) {

    $markup = "";

    if (!empty($rules)) {
      $this->setRules($rules);
    }

    // Loop through all tabs.
    foreach ($tabs as $tab => $fields) {

      // Kebabify the key name for use as an element tag.
      $element = StringHelper::toKebabCase($tab);

      // Ignore specific tabs.
      if ( $this->rule('matrix') || !in_array($element, $this->tabExclusions) ) {

        if (!$this->rule('matrix')) {

          // Ensure the element has at least one hyphen within the string,
          // unless the string is a known valid HTML5 singleton.
          if ( !in_array($element, $this->elementExceptions) && !strpos($element, '-') !== false ) {
            $element = $element.'-tab';
          }

          // Comment line for the tab name.
          $markup .= $this->commentHeader($tab.' Tab', null, 1);

          // Tab open element.
          // $markup .= "\n"."<".$element.">\n";

        } else {

          $markup .= $this->commentInline($tab.' Block', null, 4, '=');

          $markup .= "\n".$this->indentContent("{% case '".$tab."' %}", 4);

        }

        // Loop through all fields for this tab.
        foreach ($fields as $field) {

          $includeField = false;

          // Predefine content and file variables for the next bit...
          $fieldContent = false;
          $fieldFile    = false;

          // Get field settings;
          $settings = json_decode($field['settings']);

          // Custom Field Types ================================================

          // If the handle matches a field alias, use a custom template instead
          if (array_key_exists($field['handle'], $this->fieldAliases)) {

            // If the field handle exists in the list field aliases array set the the associated filename
            $fieldFileName = $this->fieldAliases[$field['handle']];

            // Use the $fieldFileName to set a field type name to be used in the generated documentation.
            $fieldTypeName = array_key_exists($field['type'], $this->fieldFiles) ? $this->fieldFiles[$field['type']] : $fieldFileName;

            // Define a sample file path for the field type.
            $fieldFile = Craft::getAlias('@helpers').'/templates/_template-maker/samples/'.$fieldFileName.'.twig';

            if ( in_array($fieldFileName, $this->fieldAliasesToInclude)) {

              $includeField = true;

            }

          // Standard Field Types ==============================================

          } elseif (array_key_exists($field['type'], $this->fieldFiles)) {

            // If the field type exists in the list field files array set the the associated filename
            $fieldFileName = $this->fieldFiles[$field['type']];

            // Use the $fieldFileName to set a field type name to be used in the generated documentation.
            $fieldTypeName = $fieldFileName;

            // Change the way fields are handled if they required special rules.
            // Fields can return strings (preferably as HTML) to be rendered as markup.
            // Or target a sample field file where it's content will be used intead.
            switch ($fieldFileName) {
              case "Redactor":
                $fieldFile = $this->redactor($field, $settings);
              break;
              case "Matrix":
                $matrixContent = $this->matrix($field, $settings);
                $fieldFile = Craft::getAlias('@helpers').'/templates/_template-maker/fields/Matrix.twig';
                $this->removeRule('matrix');
              break;
              default:
                $fieldFile = Craft::getAlias('@helpers').'/templates/_template-maker/fields/'.$fieldFileName.'.twig';
            }

          }

          // Camel Case field types to include white space
          $fieldTypeName = preg_replace('/([a-z])([A-Z])/s','$1 $2', $fieldTypeName);

          // If the file exists.
          if ( !empty($fieldContent) || !empty($matrixContent) || !empty($fieldFile) && file_exists($fieldFile)) {

            // Comment line for the field name.
            $markup .= $this->commentInline($field['name'], $fieldTypeName, ($this->rule('matrix') ? 5 : 1));

            if ($includeField) {

              $destination = Craft::getAlias('@templates').'/_components/'.$fieldFileName.'.twig';

              $component = Helpers::$app->request->fileexists($destination);

              if ( !$component ) {
                copy($fieldFile, $destination);
              }

              if ( !$this->rule('matrix') ) {
                $markup .= "\n".$this->indentContent("{% include '_components/".$fieldFileName."' %}\n", 1);
              } else {
                $markup .= "\n".$this->indentContent("{% include '_components/".$fieldFileName."' with { image : block.".$field['handle'].".one } %}\n", 5);
              }

            } else {

              // Get sample file contents.
              if ( empty($fieldContent) ) {
                $fieldContent = file_get_contents($fieldFile);
              }

              // Indent all lines for.
              $fieldContent = $this->indentContent($fieldContent, ($this->rule('matrix') ? 5 : 1));

              // Replace any instances of the string 'fieldHandle', and replace it
              // with the relivant fieldHandle.
              $find    = ["<FieldHandle>", "<FieldName>", "<FieldClass>", "<FieldContent>"];
              $replace = [$field['handle'], $field['name'], StringHelper::toKebabCase($field['handle']), ($matrixContent ?? false)];

              $fieldContent = str_replace($find, $replace, $fieldContent);

              if (!$this->rule('matrix') && $this->rule('variables')) {

                $firstLine = strtok($fieldContent, "\n");

                if ( $this->startsWith($firstLine, "\t{% set") ) {

                  $this->variables .= $firstLine."\n";
                  $fieldContent = str_replace($firstLine."\n\n", '', $fieldContent);

                }

              }

              // Add modified contents to layout.
              $markup .= "\n".$fieldContent;


            }

          }

        }

        if ($this->rule('matrix')) {
          // Tab close element.
          // $markup .= "\n</".$element.">\n";
        }
      }

    }

    return $markup;

  }

  // ===========================================================================
  // Rule checker
  // ===========================================================================

  private function rule($query) {
    return in_array($query, $this->rules);
  }

  private function setRules($criteria) {
    $criteria = is_string($criteria) ? [$criteria] : $criteria;
    $this->rules = array_merge($this->rules, $criteria);
  }

  private function removeRule($rule) {
    $index = array_search($rule, $this->rules);
    if($index !== FALSE){
      unset($this->rules[$index]);
    }
  }

  // ===========================================================================
  // Set the Section and Entry type data
  // ===========================================================================

  private function setSectionAndEntrytype($sectionId, $entryTypeId) {

    // Globabbly define the current section using the section ID
    $this->section = Craft::$app->getSections()->getSectionById($sectionId);

    // globally define the section settings which contains data like the URI Format
    $this->sectionSettings = $this->section->getSiteSettings()[1];

    // Globally define all available entry types for the section
    $this->entryTypes = $this->section->getEntryTypes();

    // Pick out the entry type relevant for this page and define it globally.
    $this->entryType = $this->entryTypes[array_search($entryTypeId, array_column($this->entryTypes, 'id'))];
  }

  // ===========================================================================
  // Path Sanitiser
  // ===========================================================================

  private function pathSanitiser() {

    // Using the original URI Format for this section, clean up path
    // by removing any dynamic twig variables and Kebabifing the path.
    $path = StringHelper::toKebabCase(trim(preg_replace('/{.*?\}/m', '', $this->sectionSettings->uriFormat),'/').'/' ?? '');

    // Variations of 'home' page paths should be ignored. As this template
    // typically exists in the templates root directory.
    if ( !in_array($path, ["home", "homepage"]) ) {
      return $path;
    }
  }

  // ===========================================================================
  // Template Name Sanitiser
  // ===========================================================================

  private function templateSantiser() {

    // If there is more than one entry type associated to the current section...
    if ( count($this->entryTypes) > 1 ) {

      // Return the entrytype handle
      return $this->entryType['handle'];

    } elseif ( $this->sectionSettings->template == '_loader' || $this->sectionSettings->template == '_loader.twig' ) {
      // If a variants of the _loader or _loader.twig was used in the section template,

      // Then check on the section type to determine a filename name default
      if ( $this->section->type == 'channel' || $this->section->type == 'structure' ) {
        // _entry for channels or structures
        return '_entry';
      } else {
        // Or index for everything else
        return 'index';
      }

    } else {
      // Lastly if all else fails. Fallback to the original template name.
      // But sanitise if by removing unwanted characters and dynamic twig variables.
      return StringHelper::toKebabCase(preg_replace('/{.*?\}/m', '', $this->sectionSettings->template));
    }

  }

  // ===========================================================================
  // Tabbing
  // ===========================================================================
  /** @link https://stackoverflow.com/questions/1462720/iterate-over-each-line-in-a-string-in-php */

  private function indentContent($content, $tabs = 0, $trim = "none") {

    $separator = "\r\n";
    $tabs      = str_repeat("\t", $tabs);
    $line      = strtok($content, $separator);
    $temp      = "";
    $count     = 0;

    while ($line !== false) {

      // $temp .= ($count != 0 ? "\n" : "").$tabs.$line.($this->endsWith($line, ' #}') ? '' : "\n");
      $temp .= ($count != 0 ? "\n" : "").$tabs.$line."\n";
      $line = strtok( $separator );
      $count ++;

    }

    return $temp;

  }

  private function startsWith($haystack, $needle) {
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
  }

  private function endsWith($haystack, $needle) {
    $length = strlen($needle);

    if ($length == 0) {
      return true;
    }

    return (substr($haystack, -$length) === $needle);
  }

  // ===========================================================================
  // Commenting markup
  // ===========================================================================

  private function commentInline($heading, $suffix = null, $tabs = 0, $seperator = "-") {

    $maxLength       = 80;
    $suffix          = !empty($suffix) ? ' ['.$suffix.']' : '';
    $totalLength     = strlen($heading) + strlen($suffix) + ($tabs*$this->tabLength);
    $seperatorLength = ($maxLength - $totalLength) < 0 ? 5 : ($maxLength - $totalLength);
    $seperators      = str_repeat($seperator, $seperatorLength - 7);
    $tabs            = str_repeat("\t", $tabs);
    $comment         = "\n".$tabs."{# ".$heading." ".$seperators.$suffix." #}\n";

    return $comment;
  }

  private function commentHeader($heading, $suffix = null, $tabs = 0, $seperator = "=") {

    $maxLength       = 80;
    $suffix          = !empty($suffix) ? '['.$suffix.']' : '';
    $totalLength     = strlen($heading) + strlen($suffix) + ($tabs*$this->tabLength);
    $seperatorLength = ($maxLength - $totalLength) < 0 ? 5 : ($maxLength - $totalLength);
    $seperators      = str_repeat($seperator, $seperatorLength);
    $tabs            = str_repeat("\t", $tabs);
    $seperators1     = str_repeat($seperator, $maxLength - 8);
    $seperators2     = str_repeat(' ', $seperatorLength - 7);
    $comment         = ($seperator !== '/' ? "\n" : "").$tabs."{# ".$seperators1." #}";
    $comment        .= "\n".$tabs."{# ".$heading." ".$seperators2.$suffix." #}";
    $comment        .= "\n".$tabs."{# ".$seperators1." #}\n";

    return $comment;

  }

  // ===========================================================================
  // Special rules for specific field types
  // ===========================================================================

  // Redactor Field ------------------------------------------------------------

  private function redactor($field, $settings) {

    /*
    If the config file allows for images (within the buttons array),
    then refer to a specialised Redactor sample file that includes the
    image transform filter. This transforms images within Redacotor field to
    avoid oversize images being rendered in the fontend...
    */

    $path = Craft::$app->getPath()->getConfigPath().DIRECTORY_SEPARATOR.'redactor'.DIRECTORY_SEPARATOR.($settings->redactorConfig ?? '');

    $config = json_decode(file_get_contents($path) ?? false);

    if ( ($config->buttons ?? false) && in_array("image", $config->buttons)) {
      return Craft::getAlias('@helpers').'/templates/_template-maker/fields/Redactor_Images.twig';
    }

    // Otherwise, just return the standard Redactor sample file.

    return Craft::getAlias('@helpers').'/templates/_template-maker/fields/Redactor.twig';
  }

  // Matrix Field --------------------------------------------------------------

  private function matrix($field, $settings) {

    $tabData = [];

    $allTabs = Craft::$app->getMatrix()->getBlockTypesByFieldId($field['id']);

    // Loop all fields within all tabs and create a clean array of useful data.
    foreach ($allTabs as $tab) {
      $fields = $tab->getFields();
      foreach ($fields as $field) {
        $fieldData = $this->allFields[array_search($field->id, array_column($this->allFields, 'id'))];
        $tabData[$tab->handle][] = [
          'name'     => $field->name   ?? false,
          'handle'   => $field->handle ?? false,
          'id'       => $field->id     ?? false,
          'type'     => $fieldData['type'] ?? false,
          'settings' => $fieldData['settings'] ?? false
        ];
      }
    }

    $find    = ["entry."];
    $replace = ["block."];

    $markup  = str_replace($find, $replace, $this->getFieldData($tabData, 'matrix'));

    return $markup;

  }

}
