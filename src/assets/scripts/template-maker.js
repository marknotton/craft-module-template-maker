////////////////////////////////////////////////////////////////////////////////
// Template Maker
////////////////////////////////////////////////////////////////////////////////

class TemplateMaker {

  constructor(options) {

    // Append form to page
    $('form#main-form').after(templateMakerForm);

    // Move filename input to aesthetically better position
    $('#template-field').appendTo("#path-field > .input");

    // Template Maker form elements
    this.elements = [];
    this.elements.form      = $('form#template-maker');
    this.elements.path      = this.elements.form.find('input#path');
    this.elements.template  = this.elements.form.find('input#template');
    this.elements.overwrite = this.elements.form.find('#overwrite');
    this.elements.variables = this.elements.form.find('#variables');

    // Notices
    this.notices = [];
    this.notices.change  = this.elements.form.find('> p.notice.change');
    this.notices.success = this.elements.form.find('> p.notice.success');
    this.notices.error   = this.elements.form.find('> p.notice.error');

    // Field Layout Design form elements
    this.elements.name   = $('form#main-form input#name');
    this.elements.handle = $('form#main-form input#handle');

    // Settings
    this.timer            = null;
    this.fileExists       = false;
    this.sectionId        = options.sectionId;
    this.entryTypeId      = options.entryTypeId;
    this.defaultTemplate  = options.default;
    this.timestamp        = options.timestamp;
    this.allFiles         = options.allFiles;

    // FLD DOM change listener ------------------------------------------------
    // If any FLD elements are moved aroud show a message advising the user
    // to save the entrytype before creating a template.

    $(".fld-tabs").on('DOMSubtreeModified', (event) => {
      $(event.currentElement).unbind('DOMSubtreeModified');
      this.notices.change.show();
    });

    // Overwrite checkbox listener ---------------------------------------------

    this.elements.overwrite.on('change', (event) => {
      this.overwrite();
    })

    // Input listener for path and template fields -----------------------------

    $.merge(this.elements.path, this.elements.template).on('input', () => {
      this.sanitiser();
    });

    // Submit button listener --------------------------------------------------

    this.elements.form.on('submit', (event) => {
      event.preventDefault();
      this.submit();
    });

    // Initialisers ------------------------------------------------------------

    this.stamp();
    this.sanitiser();
    this.fieldUpdater();

  }

  // ===========================================================================
  // Handle the overwrite class that effects styling on the form
  // ===========================================================================

  overwrite (bool) {

    if ( bool !== 'undefined' && typeof(bool) === "boolean" ) {
      this.elements.overwrite.prop('checked', bool);
    }

    if (this.elements.overwrite.is(':checked')) {
      this.elements.form.addClass('overwrite');
    } else {
      this.elements.form.removeClass('overwrite');
    }
  }

  // ===========================================================================
  // Update timestamp data attribute and also return the timestamp as a string
  // ===========================================================================

  stamp (stamp = this.timestamp) {
    $('#template-field').attr('data-timestamp', stamp);
    return this.timestamp = stamp;
  }

  // ===========================================================================
  // File and Template name sanitiser. Triggers on input changes
  // ===========================================================================

  sanitiser () {

    // Path Name ---------------------------------------------------------------

    // Trim any slashes at the start of the path.
    // Only allow up to one slash within the string (no double slashes)
    // Kebabify the string

    this.path = this.elements.path.first().val().replace(/\/\/+/g, '/').replace(/^\/+/g, '').replace(/[^a-zA-Z0-9-_/]/g, '')
    this.elements.path.first().val(this.path);

    // Template Name -----------------------------------------------------------

    clearTimeout(this.timer);

    // If the template value is empty
    if (this.elements.template.val().length == 0) {
      // Start a one second timer
      this.timer = setTimeout(() => {
        // If the timer finishes, force the template value back to a default
        // so the template file name fallbacks to something. Then sanitise.
        this.elements.template.val(this.defaultTemplate);
        this.sanitiser();
      }, 1000);
    } else {
      // If the template has a value, lower case the and kebabify the string.
      this.template = this.elements.template.val().replace(/[^a-zA-Z0-9-_]/g, '').toLowerCase();
      this.elements.template.val(this.template);
    }

    // Validate
    this.validation();

  }

  // ===========================================================================
  // File and Template name validation
  // ===========================================================================

  validation () {

    // Set the filename by concatinating the path and templates names.
    // Ensuring that trailing slashes, and the file extensions is added.
    let fullTemplatePath = (this.elements.path.val().replace(/\/$/, "")+ '/' +this.elements.template.val() + '.twig').replace(/^\//, '');

    // If file name already exists add a class to the form.
    if (this.allFiles.includes(fullTemplatePath)) {
      this.fileExists = true;
      this.notices.success.hide();
      this.elements.form.addClass('exists');
    } else {
      // If not, remove it.
      this.fileExists = false;
      this.elements.overwrite.prop('checked', false);
      this.elements.form.removeClass('exists');
    }

    // Update the <em> text.
    $('warning-message > p em').text(fullTemplatePath);
  }

  // ===========================================================================
  // Template field smart updater
  // ===========================================================================

  fieldUpdater () {

    // On page load, if the default template maker file name and path is set to exist,
    // and the template value is specifically '_entry' andthe handle input is blank,
    // Then begin some UI that updates the template field on-the-fly
    // whilst the name or handle is being edited.
    if (this.fileExists && this.elements.template.val() == '_entry' && this.elements.handle.val() == "") {

      var allowBlur = true;

      // If name input is selected and the tab keyboard key is used to move down
      // to the 'handle' input, temporily disable the unbinding of 'change input'
      // on the 'handle' input. This is similar behavour to how Craft do theres.
      this.elements.name.keydown((event) => {
        allowBlur = false;
        if (event.keyCode == 9 && !event.shiftKey) {
          setTimeout(function(){ allowBlur = true; }, 1000);
        }
      });

      // When name input or handle input is blurred (clicked or tabbed out),
      // then remove the binder tha amends the template field on-the-fly.
      $.merge(this.elements.name, this.elements.handle).blur((event) => {
        if ( allowBlur && this.elements.handle.val() !== '') {
          console.log("No more smart template name making");
          this.elements.handle.unbind('change input');
        }
      });

      // Update the template field on-the-fly whilst the handle input changes.
      this.elements.handle.on('change input', (event) => {
        this.elements.template.val(this.elements.handle.val().replace(/[^a-zA-Z0-9-_]/g, '').toLowerCase());
        if ( this.elements.template.val() == this.elements.path.val()) {
          this.elements.template.first().val('index');
        }
        this.validation();
      });

    }

  }

  // ===========================================================================
  // Form submission
  // ===========================================================================

  submit () {

    let addTimeStamp = false;

    if ( this.fileExists ) {

      if ( this.elements.overwrite.is(":checked") ) {
        // If overwrite is checked, show a popup message with one final warning.
        if (!confirm("You are about to overwrite: "+$('warning-message > p em').text()+".\n Are you sure you want to do this? This can not be undone.")) {
          setError('Template file was not created');
          return false;
        }
      } else {

        addTimeStamp = true;

      }
    }

    // Apply the loading class to disable any further input and show the animation.
    this.elements.form.addClass('loading');
    this.notices.change.hide();

    fetch('/template-maker', {
      mode    : 'cors',
      method  : 'POST',
      headers : new Headers({
        'Content-Type'     : 'application/json',
        'Accept'           : 'application/json',
        'X-Requested-With' : 'fetch'
      }),
      body : JSON.stringify({
        sectionId   : this.sectionId,
        entryTypeId : this.entryTypeId,
        path        : this.path,
        template    : this.template,
        timestamp   : addTimeStamp ? '_'+this.timestamp : '',
        variables   : this.elements.variables.is(':checked')
      }),
      credentials : 'same-origin',
    })
    .then(response => {
      response.json().then(data => {
        if (response.ok && !data.error) {
          this.allFiles.push(data.templatePath);
          this.overwrite(false);
          this.stamp(data.newTimestamp);
          this.sanitiser();
          setNotice('Template Created');
          this.elements.form.removeClass('loading');
          this.notices.success.show().find('em').text(data.templateSystemPath);
          this.notices.error.hide();
          console.log(data);
          return data;
        } else {
          setError('Failed to created Template');
          this.elements.form.addClass('error');
          this.notices.error.show().find('em').text(data.templatePath || this.template+'.twig')
          this.notices.error.find('span').text(data.message);
          this.notices.success.hide();
          setTimeout(() => { this.elements.form.removeClass('error loading') }, 1000);
          console.error(data.message);
          return Promise.reject({status: response.status, data})
        }
      })
    })
    .catch(error => {
      if ( error ) {
        console.error('Error:', error.data.message)
      } else {
        console.error('Unknown error')
      }
    })

  }

}

// After the DOM has loaded check if:
// - Fetch method exists in the window object
// - Craft's own FLD function exists to ensure this script is loading on the right page.
// - The `templateMakerOptions` variable with the options must exists.
// - Make sure there is at least one tab created before.

var templatemaker = null;

$(function() {

  if ( typeof templateMakerOptions !== 'undefined' && 'fetch' in window && typeof initFLD !== 'undefined' && $.trim($('#fieldlayoutform .fld-tabs').html()) !== '' ) {

    templatemaker = new TemplateMaker(templateMakerOptions);

  }

});
