/**
 * @file
 * Javascript support for a Node edit form for a Show.
 */

(function ($, Drupal) {

  /**
   * Show warning message when changing the Editorial Genre field.
   *
   * @type {{attach: Drupal.behaviors.kcts9MediaManagerEditorialGenreWarning.attach}}
   */
  Drupal.behaviors.kcts9MediaManagerEditorialGenreWarning = {
    attach: function (context, settings) {
      $('input[name="field_editorial_genre[target_id]"]', context)
        .once('kcts9MediaManagerEditorialGenreWarning')
        .each(function () {
          var $warning = $('div[data-drupal-selector="edit-genre-change-warning"]');
          $(this).bind('focus', function (e) {
            $warning.show();
          });
          $(this).bind('blur', function (e) {
            $warning.hide();
          });
          $(this).bind('change', function (e) {
            $warning.show();
            // Keep the warning message present.
            $(this).unbind('blur');
            $(this).unbind('change');
            $(this).unbind('focus');
          });
      });
    }
  };

})(jQuery, Drupal);
