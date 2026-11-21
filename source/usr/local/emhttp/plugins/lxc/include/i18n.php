<?php
/**
 * Plugin-local translations.
 *
 * Unraid's own translation layer only covers strings wrapped in _(...)_ and
 * only knows the entries shipped with the language packs. The plugin ships its
 * dictionary instead (languages/<locale>.php), so a translation is available as
 * soon as the plugin is installed and does not depend on a language pack
 * release.
 *
 * Lookups use the English string as the key and fall back to it, so an
 * untranslated or unknown locale renders exactly what the page always showed.
 */

/**
 * Locale selected for this request. Translations.php publishes $locale as a
 * global before any page body runs; the session value is the fallback when that
 * include is not in play (ajax requests).
 */
function lxc_locale()
{
  if (!empty($GLOBALS['locale'])) {
    return (string)$GLOBALS['locale'];
  }
  if (!empty($_SESSION['locale'])) {
    return (string)$_SESSION['locale'];
  }
  return '';
}

/**
 * Dictionary for a locale, or an empty array when the plugin does not ship one.
 * The locale is used as a file name, so anything that is not a plain locale
 * code is rejected rather than being allowed to walk out of the directory.
 */
function lxc_catalog($locale = null)
{
  static $catalogs = array();

  $locale = $locale === null ? lxc_locale() : (string)$locale;
  // Normalise the shapes a locale can arrive in (zh_CN, zh-CN, zh_CN.UTF-8)
  // to the file name stem the plugin ships, and reject anything else.
  if (!preg_match('/^([A-Za-z]{2,3})(?:[_-]([A-Za-z]{2,4}))?(?:\.[\w.-]+)?$/', $locale, $m)) {
    return array();
  }
  $locale = strtolower($m[1]) . (isset($m[2]) ? '_' . strtoupper($m[2]) : '');
  if (!array_key_exists($locale, $catalogs)) {
    $file = dirname(__DIR__) . '/languages/' . $locale . '.php';
    $catalogs[$locale] = is_file($file) ? (array)require $file : array();
  }
  return $catalogs[$locale];
}

/**
 * Translate one string. Unknown strings come back unchanged.
 */
function lxc_t($text)
{
  $catalog = lxc_catalog();
  return isset($catalog[$text]) ? $catalog[$text] : $text;
}

/**
 * Translate a format string and fill its placeholders (vsprintf rules).
 */
function lxc_tf($text)
{
  $args = array_slice(func_get_args(), 1);
  return vsprintf(lxc_t($text), $args);
}

/**
 * Publish the dictionary to the browser and define the helpers the plugin's
 * JavaScript uses (window.LXC_T / window.LXC_TF). Emit this before js/lxc.js is
 * loaded, because that script is evaluated immediately.
 */
function lxc_i18n_js()
{
  $catalog = lxc_catalog();
  $locale = lxc_locale();
  if ($locale === '') {
    $locale = 'en_US';
  }
  $json = json_encode(
    $catalog,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
  );
  if ($json === false) {
    $json = '{}';
  }
  return '<script>'
    . 'window.LXC_I18N = ' . $json . ';'
    // The task runner is a separate process, so the language the page uses has
    // to travel with the command line as well.
    . 'window.LXC_LOCALE = ' . json_encode($locale) . ';'
    . 'window.LXC_T = function (s) {'
    . '  return (window.LXC_I18N && Object.prototype.hasOwnProperty.call(window.LXC_I18N, s))'
    . '    ? window.LXC_I18N[s] : s;'
    . '};'
    . 'window.LXC_TF = function (s) {'
    . '  var args = Array.prototype.slice.call(arguments, 1), next = 0;'
    . '  return window.LXC_T(s).replace(/%(\d+\$)?[sd]/g, function (m, pos) {'
    . '    var i = pos ? parseInt(pos, 10) - 1 : next++;'
    . '    return i < args.length ? args[i] : "";'
    . '  });'
    . '};'
    . '</script>';
}