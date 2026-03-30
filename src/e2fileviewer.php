<?php
########################################################
#################### E2 FILEVIWER ######################
################ VERSION 3.1 (2026-03) #################
########################################################
/*
* =====================================================================
* ABOUT THIS FILE
* =====================================================================
*
* What you're looking at is one file that does the work of many.
*
* Built in pieces. Delivered as one.
*
* Why? Because you shouldn't have to care about folder structures,
* dependencies, or "how it works under the hood". You just want
* a file explorer that works.
*
*
* WHAT'S INSIDE?
* ---------------------------------------------------------------------
*
* - A full file explorer with gallery mode
* - Image thumbnails, video previews, PDF support
* - Upload, delete, share – all optional
* - Password protection when you need it
* - Smart caching for speed
*
*
* NO:
*   database • installation • dependencies • configuration hell
*
* YES:
*   upload • run • enjoy
*
*
* The code is modular behind the scenes. The one-file delivery
* is by design. It's not lazy – it's thoughtful.
*
* =====================================================================
* ARCHITECTURE OVERVIEW
* =====================================================================
*
* Backend (PHP)
*   ├── FV_Core          – Config, path handling, breadcrumbs, debug
*   ├── FV_FileSystem    – File operations, security, structure cache
*   │       └── FV_StructureCache – Snapshot-based JSON caching
*   ├── FV_UI            – HTML rendering (folders, files, thumbnails)
*   ├── FV_ActionHandler – POST actions: login, upload, delete (CSRF)
*   ├── FV_Image         – Image processing, EXIF, thumbnails
*   ├── FV_AssetHandler  – Static assets (JS, CSS, fonts, logos)
*   └── FV_Lang / FV_Helper – i18n, formatting, MIME, CSRF helpers
*
* Frontend (JavaScript)
*   ├── core.js          – DOM helpers, notifications, checkboxes
*   ├── popover.js       – Backdrop for login/upload popovers
*   ├── explorer.js      – Bulk actions, share links, item selection
*   ├── sort.js          – Sorting form auto-submit
*   ├── elayer.js        – Lightbox (images, video, audio, iframe)
*   └── page.js          – Loading bar, upload progress, link interceptor
*
* CSS (Responsive, CSS variables)
*   ├── core.css         – Global variables, base styles
*   ├── page.css         – Layout, header, footer, messages
*   ├── elist.css        – File/folder list styling
*   ├── elayer.css       – Lightbox overlay and controls
*   └── popover.css      – Popover forms + animated loading bar
*
* =====================================================================
* TECHNICAL HIGHLIGHTS (feature list)
* =====================================================================
*
* CSRF protection, MIME type validation, snapshot-based structure caching,
* hierarchical cache invalidation, dynamic image resizing, EXIF metadata
* extraction, auto‑rotation from orientation tag, WebP thumbnail generation,
* multi‑language support (de/en/tr), asset bundling via PHP, popover with
* backdrop, lightbox with gesture zoom, upload progress bar, configurable
* hide‑extensions with exception rules, read‑in mode (file streaming),
* password hashing (Argon2id/BCrypt/SHA256), demo mode, image boosting,
* intelligent thumbnail caching, recursive cache cleanup, magic string
* support for ignore lists, and full responsiveness.
*
* =====================================================================
*/
?>
<?php
if (!function_exists('str_starts_with')) {
function str_starts_with($haystack, $needle)
{
if ($needle === '') {
return true;
}
return strncmp($haystack, $needle, strlen($needle)) === 0;
}
}
if (!function_exists('str_ends_with')) {
function str_ends_with($haystack, $needle)
{
if ($needle === '') {
return true;
}
$len = strlen($needle);
return substr($haystack, -$len) === $needle;
}
}
if (!function_exists('str_contains')) {
function str_contains($haystack, $needle)
{
return $needle === '' || strpos($haystack, $needle) !== false;
}
}
?>
<?php
/**
* FV_Core – Hauptklasse des E2 FileViewers
* ===================================
*
* Diese Klasse ist das Herzstück des FileViewers. Sie verwaltet die
* Konfiguration, Pfadauflösung und die Breadcrumb-Navigation.
* Dateisystem-Operationen wurden in die FV_FileSystem-Klasse ausgelagert.
*
*
* KERN-FUNKTIONEN
* ---------------
* - Konfigurations-Laden aus _fv-config.php
* - Pfad-Normalisierung und URL-Erzeugung
* - Breadcrumb-Navigation (pathList)
* - Verwaltung der statischen Konfiguration
* - Debug-Infrastruktur für alle Komponenten
*
*
* AUSGELAGERTE FUNKTIONEN (in FV_FileSystem)
* ---------------------------------------
* - readDirInfo, readFileInfo (Ordner-/Datei-Auslesen)
* - erase, createDir (Dateisystem-Operationen)
* - secureFileLocation, normalizeRelativePath (Sicherheit)
* - isExtensionHidden, isIgnoredItem (Filter)
* - clearCacheElemsByDir, clearCacheElemByLocation (Cache)
*
*
* DEBUG-KONFIGURATION
* -------------------
* In der _fv-config.php kann 'debug' gesetzt werden:
*   'debug' => 'all'                    // alle Debug-Ausgaben
*   'debug' => 'fv,filesystem,template' // nur bestimmte Komponenten
*
*
* VERWENDUNG
* ----------
*   FV_Core::construct(); // Initialisierung am Skript-Beginn
*   $dirInfo = FV_FileSystem::readDirInfo('/pfad/zum/ordner/', 1); // via FV_FileSystem
*   $pathList = FV_Core::pathList(); // Breadcrumb-Array
*
* =====================================================================
*/
class FV_Core
{
########################### STATISCHE ÖFFENTLICHE KONFIGURATION (KANN IN _fv-config.php ÜBERSCHRIEBEN WERDEN)
##### BASIS-PFADE UND DATEINAMEN
static public string $insFilename;
static public bool $readInMode             = false;
static public string $cfgFilename          = '_fv-config.php';
static public string $adminPassword        = '$argon2id$v=19$m=65536,t=4,p=1$d0ZBQ1Fyd0h4aFJ2V3psOA$VPcB6MFFtbqyhbNFE01F2k0fzufMrIeBGecvBy8ePYU';
static public bool $enableDelete           = false;
static public bool $enableUpload           = false;
static public bool $enableDemo             = false;
static public bool $autoLoggedIn           = false;
static public bool $requireLoginForRead    = false;
static public bool $enableImageCache       = true;
static public bool $enableStructureCache   = true;
static public bool $enableImageBoost       = false;
static public int $thumbPixels             = 360;
static public int $imagePixels             = 1200;
static public bool $showHideExtensionsInfo = true;
static public bool $showCacheFolder        = true;
static public string $customLogoUrl        = '';
static public string $customFaviconUrl     = '';
static public string $customCssUrl         = '';
static public bool $enableMimeValidation   = true;
static public string $mimeTypeMappingAdd   = '';
static public string $defaultMimeMapping   = 'jpg:image/jpeg, jpeg:image/jpeg, png:image/png, gif:image/gif, webp:image/webp, pdf:application/pdf, mp4:video/mp4, m4v:video/mp4, mov:video/quicktime, avi:video/x-msvideo, mkv:video/x-matroska, mp3:audio/mpeg, wav:audio/wav, ogg:audio/ogg, flac:audio/flac, txt:text/plain, html:text/html, htm:text/html, css:text/css, js:application/javascript, json:application/json, xml:application/xml, zip:application/zip, rar:application/x-rar-compressed, 7z:application/x-7z-compressed, doc:application/msword, docx:application/vnd.openxmlformats-officedocument.wordprocessingml.document, xls:application/vnd.ms-excel, xlsx:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, ppt:application/vnd.ms-powerpoint, pptx:application/vnd.openxmlformats-officedocument.presentationml.presentation, heic:image/heic, heif:image/heif';
static public string $mimeTypeMapping      = '';
static public string $mimeTypeMappingBlock = '';
static private $debugConfig                = false;
static private $insDir                     = false;
static private $insPath                    = false;
static private $scanDir                    = false;
static private $scanPath                   = false;
static private $cacheDir                   = false;
static private $relativePathString         = false;
##### PRÜFT, OB DEBUG FÜR EINE KOMPONENTE AKTIVIERT IST
public static function isDebugEnabled(string $component = 'all'): bool
{
if (self::$debugConfig === false || self::$debugConfig === '') {
return false;
}
if (self::$debugConfig === 'all') {
return true;
}
$enabledComponents = array_map('trim', explode(',', strtolower(self::$debugConfig)));
return in_array($component, $enabledComponents);
}
##### INTERNER DEBUG-AUSGABE (NUR FÜR FV_CORE)
private static function debug(string $message): void
{
if (self::isDebugEnabled('fv')) {
error_log("[FV_Core] " . $message);
}
}
########################### KONSTRUKTOR & INITIALISIERUNG
##### HAUPT-INITIALISIERUNG: LÄDT KONFIGURATION, SETZT PFADE
public static function construct(): void
{
self::debug("========== construct START ==========");
$fileParts = self::pathInfo($_SERVER['SCRIPT_FILENAME']);
self::$insFilename = $fileParts['basename'];
self::debug("construct: insFilename = " . self::$insFilename);
$part = parse_url($_SERVER['REQUEST_URI']);
$path = FV_Helper::slashBeautifier($part['path']);
self::debug("construct: raw path from URI = " . $path);
//-- Dateinamen aus Pfad entfernen
if (str_ends_with($path, (string)self::$insFilename)) {
$path = substr($path, 0, -strlen((string)self::$insFilename));
$path = rtrim($path, '/');
self::debug("construct: entfernte Dateinamen, neuer path = " . $path);
}
self::$insPath = $path;
self::debug("construct: self::\$insPath gesetzt = " . self::$insPath);
self::$relativePathString = (!empty($_GET['fvd'])) ? (string) $_GET['fvd'] : '';
self::debug("insFilename: " . self::$insFilename);
self::debug("insPath: " . self::$insPath);
self::debug("relativePathString: " . self::$relativePathString);
//-- Konfiguration laden
self::loadConfig();
self::debug("========== construct ENDE ==========");
self::debug("Finale Einstellungen:");
self::debug("scanDir: " . self::scanDir());
self::debug("cacheDir: " . self::cacheDir());
self::debug("enableStructureCache: " . (self::$enableStructureCache ? 'JA' : 'NEIN'));
self::debug("showCacheFolder: " . (self::$showCacheFolder ? 'JA' : 'NEIN'));
self::debug("self::\$insPath: " . self::$insPath);
self::debug("enableMimeValidation: " . (self::$enableMimeValidation ? 'JA' : 'NEIN'));
}
########################### KONFIGURATION LADEN
##### LÄDT DIE KONFIGURATION AUS _fv-config.php
private static function loadConfig(): void
{
//-- Konfigurationsdatei einbinden, falls vorhanden
if (file_exists(self::insDir() . self::$cfgFilename)) {
self::debug("Config-Datei gefunden: " . self::insDir() . self::$cfgFilename);
include_once self::insDir() . self::$cfgFilename;
} else {
self::debug("Keine Config-Datei gefunden");
return;
}
//-- Konfigurations-Variablen aus _fv-config.php parsen
if (!defined('_fvconfig')) {
self::debug("_fvconfig nicht definiert");
return;
}
$conArr = json_decode(_fvconfig, true, 2);
if (empty($conArr)) {
self::debug("Config leer oder ungültig");
return;
}
self::debug("Config geladen, Schlüssel: " . implode(', ', array_keys($conArr)));
//-- Basis-Konfiguration
self::setConfigValue('adminPassword', $conArr['adminPassword'] ?? null, 'adminPassword', 'string');
self::setConfigValue('scanDir', $conArr['scanDir'] ?? null, 'scanDir', 'path');
self::setConfigValue('scanRootUrl', $conArr['scanRootUrl'] ?? null, 'scanPath', 'url');
self::setConfigValue('cacheDir', $conArr['cacheDir'] ?? null, 'cacheDir', 'path');
self::setConfigValue('language', $conArr['language'] ?? null, 'language', 'language');
//-- Boolesche Einstellungen
self::setConfigValue('enableDemo', $conArr['enableDemo'] ?? null, 'enableDemo', 'bool');
self::setConfigValue('enableImageBoost', $conArr['enableImageBoost'] ?? null, 'enableImageBoost', 'bool');
self::setConfigValue('autoLoggedIn', $conArr['autoLoggedIn'] ?? null, 'autoLoggedIn', 'bool');
self::setConfigValue('enableImageCache', $conArr['enableImageCache'] ?? null, 'enableImageCache', 'bool');
self::setConfigValue('enableStructureCache', $conArr['enableStructureCache'] ?? null, 'enableStructureCache', 'bool');
self::setConfigValue('enableDelete', $conArr['enableDelete'] ?? null, 'enableDelete', 'bool');
self::setConfigValue('enableUpload', $conArr['enableUpload'] ?? null, 'enableUpload', 'bool');
self::setConfigValue('readInMode', $conArr['readInMode'] ?? null, 'readInMode', 'bool');
self::setConfigValue('showHideExtensionsInfo', $conArr['showHideExtensionsInfo'] ?? null, 'showHideExtensionsInfo', 'bool');
self::setConfigValue('showCacheFolder', $conArr['showCacheFolder'] ?? null, 'showCacheFolder', 'bool');
self::setConfigValue('requireLoginForRead', $conArr['requireLoginForRead'] ?? null, 'requireLoginForRead', 'bool');
self::setConfigValue('enableMimeValidation', $conArr['enableMimeValidation'] ?? null, 'enableMimeValidation', 'bool');
// enableCaching setzt beide Cache-Werte
if (isset($conArr['enableCaching']) && is_bool($conArr['enableCaching'])) {
self::$enableImageCache = $conArr['enableCaching'];
self::$enableStructureCache = $conArr['enableCaching'];
self::debug("enableCaching: " . ($conArr['enableCaching'] ? 'JA' : 'NEIN'));
}
//-- Integer Einstellungen
self::setConfigValue('thumbPixels', $conArr['thumbPixels'] ?? null, 'thumbPixels', 'int');
self::setConfigValue('imagePixels', $conArr['imagePixels'] ?? null, 'imagePixels', 'int');
//-- URLs
self::setConfigValue('customLogoUrl', $conArr['customLogoUrl'] ?? null, 'customLogoUrl', 'string');
self::setConfigValue('customFaviconUrl', $conArr['customFaviconUrl'] ?? null, 'customFaviconUrl', 'string');
self::setConfigValue('customCssUrl', $conArr['customCssUrl'] ?? null, 'customCssUrl', 'string');
//-- MIME-Validation Einstellungen
if (isset($conArr['enableMimeValidation']) && is_bool($conArr['enableMimeValidation'])) {
self::$enableMimeValidation = $conArr['enableMimeValidation'];
self::debug("enableMimeValidation: " . (self::$enableMimeValidation ? 'JA' : 'NEIN'));
}
if (!empty($conArr['mimeTypeMappingAdd']) && is_string($conArr['mimeTypeMappingAdd'])) {
self::$mimeTypeMappingAdd = $conArr['mimeTypeMappingAdd'];
self::debug("mimeTypeMappingAdd: " . self::$mimeTypeMappingAdd);
//-- Bereinigung: Blockierte und erlaubte Extensions trennen
$allowedParts = [];
$blockedParts = [];
$parts = array_map('trim', explode(',', self::$mimeTypeMappingAdd));
foreach ($parts as $part) {
if (str_starts_with($part, '!')) {
// Blockierte Extension
$blockedExt = FV_FileSystem::cleanExtension(substr($part, 1));
if ($blockedExt !== '') {
$blockedParts[] = $blockedExt;
}
} else {
// Erlaubte Extension: MIME-Paar
$pair = array_map('trim', explode(':', $part));
if (count($pair) === 2) {
$ext = FV_FileSystem::cleanExtension($pair[0]);
if ($ext !== '') {
$allowedParts[] = $ext . ':' . $pair[1];
}
}
}
}
// Zusammenbauen
self::$mimeTypeMappingBlock = implode(',', $blockedParts);
self::$mimeTypeMapping = implode(',', $allowedParts);
self::debug("mimeTypeMapping (erlaubt): " . self::$mimeTypeMapping);
self::debug("mimeTypeMappingBlock (blockiert): " . self::$mimeTypeMappingBlock);
}
//-- Debug-Konfiguration
self::setConfigValue('debug', $conArr['debug'] ?? null, 'debugConfig', 'debug');
//-- Versteckte Erweiterungen konfigurieren
if (!empty($conArr['hideExtensions']) && is_string($conArr['hideExtensions'])) {
$arr = explode(',', $conArr['hideExtensions']);
if (!empty($arr)) {
$ac = [];
foreach ($arr as $mime) {
$cleaned = FV_FileSystem::cleanExtension($mime);
if ($cleaned !== '') {
$ac[] = $cleaned;
}
}
FV_FileSystem::$hideExtensions = implode(',', array_unique($ac));
}
}
if (!empty($conArr['hideExtensionsAdd']) && is_string($conArr['hideExtensionsAdd'])) {
$arr = explode(',', $conArr['hideExtensionsAdd']);
if (!empty($arr)) {
$ac = [];
if (!empty(FV_FileSystem::$hideExtensions)) {
foreach (explode(',', FV_FileSystem::$hideExtensions) as $mime) {
$ac[$mime] = $mime;
}
}
foreach ($arr as $mime) {
$cleaned = FV_FileSystem::cleanExtension($mime);
if ($cleaned === '') {
continue;
}
if (str_starts_with($mime, '!')) {
$baseExt = FV_FileSystem::cleanExtension(substr($mime, 1));
if ($baseExt !== '') {
$ac['!' . $baseExt] = '!' . $baseExt;
if (isset($ac[$baseExt])) {
unset($ac[$baseExt]);
}
}
} else {
$ac[$cleaned] = $cleaned;
if (isset($ac['!' . $cleaned])) {
unset($ac['!' . $cleaned]);
}
}
}
FV_FileSystem::$hideExtensions = implode(',', array_unique($ac));
}
}
//-- Ignorierte Items konfigurieren (als String)
if (!empty($conArr['ignoreItems']) && is_string($conArr['ignoreItems'])) {
$arr = explode(',', $conArr['ignoreItems']);
if (!empty($arr)) {
$ac = [];
foreach ($arr as $item) {
$cleaned = trim($item);
if ($cleaned !== '') {
$ac[] = $cleaned;
}
}
FV_FileSystem::$ignoreItems = implode(',', $ac);
}
}
//-- Sprache Fallback
if (empty($conArr['language']) || !in_array($conArr['language'], FV_Lang::languageList())) {
if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
$l = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
if (in_array($l, FV_Lang::languageList())) {
FV_Lang::language($l);
}
}
}
}
##### VALIDIERT UND SETZT EINEN CONFIG-WERT
private static function setConfigValue(string $key, $value, string $target, string $type): void
{
if ($value === null || $value === '') return;
switch ($type) {
case 'bool':
if (is_bool($value)) {
self::${$target} = $value;
} elseif (is_int($value) && ($value === 0 || $value === 1)) {
self::${$target} = (bool)$value;
}
break;
case 'int':
if (is_int($value)) {
self::${$target} = $value;
} elseif (is_numeric($value)) {
self::${$target} = (int)$value;
}
break;
case 'string':
if (is_string($value) && !empty($value)) {
self::${$target} = $value;
}
break;
case 'path':
if (is_string($value) && !empty($value)) {
$path = FV_Helper::slashBeautifier($value);
if (!str_ends_with($path, '/')) {
$path .= '/';
}
$real = realpath($path);
if ($real !== false) {
$path = FV_Helper::slashBeautifier($real);
if (!str_ends_with($path, '/')) {
$path .= '/';
}
}
self::${$target} = $path;
}
break;
case 'url':
if (is_string($value) && !empty($value)) {
self::${$target} = rtrim(FV_Helper::slashBeautifier($value), '/') . '/';
}
break;
case 'language':
if (is_string($value) && !empty($value) && in_array($value, FV_Lang::languageList())) {
FV_Lang::language($value);
}
break;
case 'debug':
if (is_string($value) && !empty($value)) {
self::${$target} = $value;
} elseif ($value === false || $value === null) {
self::${$target} = false;
}
break;
}
}
########################### PFAD-METHODEN
##### GIBT DAS INSTALLATIONS-VERZEICHNIS ZURÜCK
public static function insDir(): string
{
if (self::$insDir === false) {
self::$insDir = FV_Helper::slashBeautifier(realpath(dirname(__FILE__)) . '/');
}
return (string)self::$insDir;
}
##### GIBT DEN INSTALLATIONS-PFAD (URL) ZURÜCK
public static function insPath(bool $forceIndex = false): string
{
$base = rtrim(FV_Helper::slashBeautifier((string)self::$insPath), '/');
self::debug("insPath($forceIndex) ENTRY: self::\$insPath = '" . self::$insPath . "', base = '$base'");
if (str_ends_with($base, (string)self::$insFilename)) {
$base = substr($base, 0, -strlen((string)self::$insFilename));
$base = rtrim($base, '/');
self::debug("insPath: entfernte Dateinamen aus base, neue base = '$base'");
}
$result = '';
if ($forceIndex === true) {
if (!str_contains($_SERVER['REQUEST_URI'], (string)self::$insFilename)) {
$result = $base . '/';
self::debug("insPath: forceIndex=true, URI ohne Dateinamen -> result = '$result'");
} else {
$result = $base . '/' . self::$insFilename;
self::debug("insPath: forceIndex=true, URI mit Dateinamen -> result = '$result'");
}
} else {
$result = $base . '/';
self::debug("insPath: forceIndex=false -> result = '$result'");
}
self::debug("insPath($forceIndex) RETURNS: " . $result);
return $result;
}
##### GIBT DAS SCAN-VERZEICHNIS (PHYSIKALISCHER PFAD) ZURÜCK
public static function scanDir(): string
{
if (self::$scanDir === false) {
$loc = self::insDir();
} else {
$loc = self::$scanDir;
}
$result = FV_Helper::slashBeautifier(realpath((string)$loc) . '/');
self::debug("scanDir: " . $result);
return $result;
}
##### GIBT DEN SCAN-PFAD (URL) ZURÜCK
public static function scanPath(): string
{
if (self::$scanPath !== false) {
return rtrim(FV_Helper::slashBeautifier((string)self::$scanPath), '/') . '/';
} else {
return self::insPath();
}
}
##### GIBT DAS CACHE-VERZEICHNIS ZURÜCK
public static function cacheDir(bool $realPath = true): string
{
if (self::$cacheDir === false) {
$loc = self::insDir() . self::cacheDirName();
} else {
$loc = self::$cacheDir;
}
if ($realPath) {
$real = realpath((string)$loc);
if ($real === false) {
return FV_Helper::slashBeautifier((string)$loc);
}
return FV_Helper::slashBeautifier($real . '/');
} else {
return FV_Helper::slashBeautifier((string)$loc);
}
}
##### GIBT DEN STANDARD-CACHE-ORDNER-NAMEN ZURÜCK
public static function cacheDirName(): string
{
return '_fv-cache';
}
##### GIBT DIE LOGO-URL ZURÜCK (CUSTOM ODER STANDARD)
public static function getLogoUrl(): string
{
if (!empty(self::$customLogoUrl)) {
return (string)self::$customLogoUrl;
}
return self::insPath(true) . '?file=logo';
}
##### GIBT DIE FAVICON-URL ZURÜCK
public static function getFaviconUrl(): string
{
if (!empty(self::$customFaviconUrl)) {
return (string)self::$customFaviconUrl;
}
return self::insPath(true) . '?file=logoSmall';
}
##### GIBT DIE CUSTOM-CSS-URL ZURÜCK
public static function getCustomCssUrl(): string
{
if (!empty(self::$customCssUrl)) {
return (string)self::$customCssUrl;
}
return '';
}
########################### PFAD-LISTE (BREADCRUMB)
##### ERSTELLT DIE PFAD-LISTE FÜR DIE BREADCRUMB-NAVIGATION
public static function pathList($modus = false, $relPath = false)
{
self::debug("pathList ENTRY: modus='$modus', relPath='$relPath'");
if ($relPath === false) {
$relPath = self::$relativePathString;
}
self::debug("pathList: relPath nach default = '$relPath'");
//-- Dateinamen aus Pfad entfernen
if (str_contains((string)$relPath, (string)self::$insFilename)) {
$oldRelPath = $relPath;
$relPath = preg_replace('#/?' . preg_quote((string)self::$insFilename, '#') . '/?#', '', (string)$relPath);
$relPath = rtrim((string)$relPath, '/');
self::debug("pathList: entfernte Dateinamen aus relPath: '$oldRelPath' -> '$relPath'");
}
$return   = false;
$nameList = [];
$InfoList = [];
$cleanRelative = FV_FileSystem::normalizeRelativePath((string)$relPath);
if ($cleanRelative === false) {
self::debug("pathList: normalizeRelativePath false -> return null");
return null;
}
$fullPath = self::scanDir() . $cleanRelative;
self::debug("pathList: fullPath = '$fullPath'");
if (is_file($fullPath)) {
$fullPath = dirname($fullPath);
self::debug("pathList: fullPath ist Datei, neuer fullPath = '$fullPath'");
}
$location = FV_FileSystem::secureFileLocation($fullPath);
if ($location) {
$relative = substr($location, strlen(self::scanDir()));
self::debug("pathList: location='$location', relative='$relative'");
if (is_dir($location) && !str_ends_with($relative, '/')) {
$relative .= '/';
self::debug("pathList: relative mit Slash ergänzt = '$relative'");
}
$list = explode('/', $relative);
if (!empty($list)) {
self::debug("pathList: list = " . print_r($list, true));
foreach ($list as $entry) {
if (!empty($entry)) {
$nameList[] = $entry;
if ($modus === 'info') {
$currentPathForInfo = implode('/', $nameList);
self::debug("pathList: info modus, currentPathForInfo = '$currentPathForInfo'");
if (str_ends_with($currentPathForInfo, (string)self::$insFilename)) {
$currentPathForInfo = substr($currentPathForInfo, 0, -strlen((string)self::$insFilename));
$currentPathForInfo = rtrim($currentPathForInfo, '/');
self::debug("pathList: info modus, entfernte Dateinamen aus currentPathForInfo = '$currentPathForInfo'");
}
$InfoList[] = [
'name'        => $entry,
'currentName' => self::pathList('currentName', $currentPathForInfo),
'currentPath' => self::pathList('currentPath', $currentPathForInfo),
'currentUrl'  => self::pathList('currentUrl', $currentPathForInfo),
'backName'    => self::pathList('backName', $currentPathForInfo),
'backPath'    => self::pathList('backPath', $currentPathForInfo),
'backUrl'     => self::pathList('backUrl', $currentPathForInfo),
];
}
}
}
}
} else {
self::debug("pathList: location false -> return null");
return null;
}
self::debug("pathList: nameList = " . print_r($nameList, true));
switch ($modus) {
case 'backPath':
$reversed = array_reverse($nameList);
unset($reversed[0]);
if (count($reversed) > 0) {
$reversed = array_reverse($reversed);
$return = implode('/', $reversed) . '/';
} else {
$return = false;
}
break;
case 'backUrl':
if (count($nameList) < 1) {
$return = false;
} else {
$reversed = array_reverse($nameList);
unset($reversed[0]);
if (count($reversed) > 0) {
$reversed = array_reverse($reversed);
$return = self::insPath(true) . '?fvd=' . urlencode(implode('/', $reversed));
self::debug("pathList: backUrl -> call insPath(true)");
} else {
$return = self::insPath(true);
self::debug("pathList: backUrl (empty) -> call insPath(true)");
}
}
break;
case 'backName':
$reversed = array_reverse($nameList);
$return = $reversed[1] ?? false;
break;
case 'currentName':
$reversed = array_reverse($nameList);
$return = $reversed[0] ?? false;
break;
case 'currentPath':
$return = count($nameList) < 1 ? '' : implode('/', $nameList) . '/';
break;
case 'currentUrl':
$return = self::insPath(true) . '?fvd=' . (count($nameList) < 1 ? '' : urlencode(implode('/', $nameList)));
self::debug("pathList: currentUrl -> call insPath(true)");
break;
case 'info':
$return = $InfoList;
break;
default:
$return = $nameList;
}
self::debug("pathList RETURN: " . (is_array($return) ? 'array' : $return));
return $return;
}
########################### PFAD-INFO (PATHINFO-ERSATZ)
##### EXTRAHIERT PFAD-INFORMATIONEN (dirname, basename, extension, filename)
public static function pathInfo(string $loc): array
{
$loc = (string) $loc;
$d = $b = $e = $f = $slashPos = false;
$lastBack = strrpos($loc, '\\');
$lastSlash = strrpos($loc, '/');
if ($lastBack !== false && $lastSlash !== false) {
$slashPos = max($lastBack, $lastSlash);
} elseif ($lastBack !== false) {
$slashPos = $lastBack;
} elseif ($lastSlash !== false) {
$slashPos = $lastSlash;
}
if ($slashPos !== false) {
$d = substr($loc, 0, $slashPos);
$b = substr($loc, $slashPos + 1);
$f = $b;
$dot = strrpos($b, '.');
if ($dot !== false) {
$e = substr($b, $dot + 1);
$f = substr($b, 0, $dot);
} elseif (str_starts_with($b, '.')) {
$e = substr($b, 1);
$f = '';
}
} else {
$dot = strrpos($loc, '.');
if ($dot !== false) {
$b = $loc;
$e = substr($loc, $dot + 1);
$f = substr($loc, 0, $dot);
} elseif (str_starts_with($loc, '.')) {
$b = $loc;
$e = substr($loc, 1);
$f = '';
}
}
return ['dirname' => $d, 'basename' => $b, 'extension' => $e, 'filename' => $f];
}
}
?>
<?php
/**
* FV_FileSystem – Dateisystem-Operationen und Sicherheitsprüfungen
* ==============================================================
*
* Diese Klasse übernimmt alle dateisystembezogenen Operationen.
* Sie kann sowohl statisch als auch als Instanz genutzt werden.
* Für die Dependency Injection in andere Klassen stehen Instanzmethoden
* mit dem Suffix "Instance" zur Verfügung, die intern die statischen Methoden aufrufen.
*
* =====================================================================
*/
class FV_FileSystem
{
########################### EIGENSCHAFTEN
public static string $hideExtensions     = 'htaccess,htpasswd,php,php3,php4,php5,php7,php8,phtml,phar,cgi,pl,py,pyc,pyo,rb,sh,bash,ksh,csh,zsh,ps1,exe,bat,cmd,com,jar,war,jsp,asp,aspx,cfm,cfml,ini,conf,cfg,config,yml,yaml,env,gitignore,git,sql,sqlite,db,bak,backup,old,orig,msi,msp,mst,vb,vbs,vbe,js,jse,wsf,wsh,scf,lnk,reg,inf,pol,cpl,scr,drv,sys,bin,run,app,command';
public static string $ignoreItems        = '$RECYCLE.BIN, System Volume Information, lost+found, .Trash-, Thumbs.db, .DS_Store, desktop.ini';
private static array $imageInfoCache     = [];
private static ?FV_StructureCache $structureCache     = null;
private static array $intToMime = [
1 => 'gif',
2 => 'jpg',
3 => 'png',
18 => 'webp',
];
########################### DEBUG-HELFER
private static function debug(string $message): void
{
if (FV_Core::isDebugEnabled('filesystem')) {
error_log("[FV_FileSystem] " . $message);
}
}
########################### HILFSMETHODEN
public static function cleanExtension(string $ext): string
{
$ext = trim((string)$ext);
if ($ext === '') {
return '';
}
return strtolower(ltrim($ext, '.'));
}
public static function isIgnoredItem(string $name, string $fullPath = ''): bool
{
self::debug("isIgnoredItem: name='$name', fullPath='$fullPath'");
$ignoreItemsArray = explode(',', self::$ignoreItems);
if (in_array($name, $ignoreItemsArray)) {
self::debug("-> IGNORIERT: name in Liste");
return true;
}
foreach ($ignoreItemsArray as $ignore) {
if (str_starts_with($name, $ignore)) {
self::debug("-> IGNORIERT: name startet mit '$ignore'");
return true;
}
if ($fullPath && (str_contains($fullPath, '/' . $ignore) || str_contains($fullPath, '\\' . $ignore))) {
self::debug("-> IGNORIERT: Pfad enthält '$ignore'");
return true;
}
}
self::debug("-> NICHT ignoriert");
return false;
}
public static function isExtensionHidden(string $ext): bool
{
self::debug("isExtensionHidden: ext='$ext'");
$return = false;
$arr = explode(',', self::$hideExtensions);
$ext = self::cleanExtension($ext);
if (!empty($arr) && $ext !== '') {
if (in_array('.', $arr)) {
$return = true;
foreach ($arr as $mimeOrAc) {
if (str_starts_with($mimeOrAc, '!') && $ext === substr($mimeOrAc, 1)) {
$return = false;
break;
}
}
} else {
if (in_array($ext, $arr)) {
$return = true;
}
}
}
self::debug("-> Ergebnis: " . ($return ? 'VERSTECKT' : 'SICHTBAR'));
return $return;
}
public static function canWrite(bool $cachedir = false): bool
{
$dir = $cachedir ? FV_Core::cacheDir() : FV_Core::scanDir();
$result = is_writable($dir);
self::debug("canWrite(cachedir=" . ($cachedir ? 'true' : 'false') . "): dir='$dir', result=" . ($result ? 'JA' : 'NEIN'));
return $result;
}
########################### PFAD-SICHERHEIT
public static function normalizeRelativePath(string $relPath)
{
self::debug("normalizeRelativePath ENTRY: '$relPath'");
$relPath = (string)$relPath;
if ($relPath === '' || $relPath === '/') {
self::debug("-> leerer Pfad, return ''");
return '';
}
$parts = explode('/', $relPath);
$cleanParts = [];
foreach ($parts as $part) {
if ($part === '' || $part === '.') {
continue;
}
if ($part === '..') {
self::debug("-> Directory Traversal erkannt, return false");
return false;
}
$cleanParts[] = $part;
}
$result = implode('/', $cleanParts);
self::debug("-> result: '$result'");
return $result;
}
public static function secureFileLocation(string $fileLoc)
{
self::debug("secureFileLocation ENTRY: '$fileLoc'");
$fileLoc = realpath((string) $fileLoc);
if (!empty($fileLoc)) {
$fileLoc = FV_Helper::slashBeautifier($fileLoc);
$infos = FV_Core::pathInfo($fileLoc);
if (!empty($infos['extension']) && self::isExtensionHidden($infos['extension'])) {
self::debug("-> Dateiendeung versteckt, return false");
return false;
}
$scanOrigin = FV_Core::scanDir();
if (
str_starts_with($fileLoc . '/', $scanOrigin) ||
str_starts_with($fileLoc, $scanOrigin)
) {
if (
$fileLoc != FV_Core::insDir() . FV_Core::$cfgFilename &&
$fileLoc != FV_Core::insDir() . FV_Core::$insFilename
) {
if (is_file($fileLoc)) {
self::debug("-> Datei gefunden: '$fileLoc'");
return $fileLoc;
} elseif (is_dir($fileLoc)) {
$result = rtrim($fileLoc, '/') . '/';
self::debug("-> Ordner gefunden: '$result'");
return $result;
}
}
}
}
self::debug("-> KEINE sichere Location gefunden, return false");
return false;
}
public static function secureWritablePath(string $relativePath, ?string $baseDir = null)
{
self::debug("secureWritablePath ENTRY: relativePath='$relativePath', baseDir='$baseDir'");
if ($baseDir === null) {
$baseDir = FV_Core::scanDir();
}
$baseDir = rtrim(FV_Helper::slashBeautifier($baseDir), '/') . '/';
$cleanRelative = self::normalizeRelativePath($relativePath);
if ($cleanRelative === false) {
self::debug("-> normalizeRelativePath false, return false");
return false;
}
if ($cleanRelative === '') {
$fullPath = $baseDir;
} else {
$fullPath = $baseDir . $cleanRelative;
}
$fullPath = FV_Helper::slashBeautifier($fullPath);
if (is_dir($fullPath) && !str_ends_with($fullPath, '/')) {
$fullPath .= '/';
}
if (!str_starts_with($fullPath, $baseDir)) {
self::debug("-> Pfad außerhalb baseDir, return false");
return false;
}
self::debug("-> sichere Location: '$fullPath'");
return $fullPath;
}
public static function isInsideCacheDir(string $path): bool
{
$cacheDirReal = rtrim(FV_Core::cacheDir(), '/') . '/';
$pathReal = rtrim($path, '/') . '/';
$result = (strpos($pathReal, $cacheDirReal) === 0);
self::debug("isInsideCacheDir: path='$path', result=" . ($result ? 'JA' : 'NEIN'));
return $result;
}
public static function isCacheFolderVisibleInPath(string $currentPath): bool
{
$cacheDirReal = rtrim(FV_Core::cacheDir(), '/') . '/';
$scanDirReal = rtrim(FV_Core::scanDir(), '/') . '/';
$currentReal = $scanDirReal . $currentPath;
$cacheParent = rtrim(dirname($cacheDirReal), '/') . '/';
$visible = ($cacheParent === $currentReal);
self::debug("isCacheFolderVisibleInPath: currentPath='$currentPath', cacheParent='$cacheParent', currentReal='$currentReal', visible=" . ($visible ? 'JA' : 'NEIN'));
return $visible;
}
########################### DATEISYSTEM-OPERATIONEN
public static function erase(string $dir_file): void
{
self::debug("erase ENTRY: '$dir_file'");
if (is_dir($dir_file)) {
$array = self::eraseFindFileAndDelete($dir_file);
$array = array_reverse($array);
foreach ($array as $elem) {
rmdir($elem);
}
self::debug("-> Ordner gelöscht");
} elseif (is_file($dir_file)) {
self::deleteFile($dir_file, true);
self::debug("-> Datei gelöscht");
}
}
private static function eraseFindFileAndDelete(string $verz, array $folder = []): array
{
self::debug("eraseFindFileAndDelete: '$verz'");
$folder[] = $verz;
$fp = opendir($verz);
while ($dir_file = readdir($fp)) {
if ($dir_file == '.' || $dir_file == '..') {
continue;
}
$neu_file = $verz . '/' . $dir_file;
if (is_dir($neu_file)) {
$folder = self::eraseFindFileAndDelete($neu_file, $folder);
} else {
self::deleteFile($neu_file, true);
self::debug("-> Datei gelöscht: '$neu_file'");
}
}
closedir($fp);
return $folder;
}
public static function createDir(string $dir, int $permission = 0777)
{
self::debug("createDir ENTRY: '$dir'");
$dir = FV_Helper::slashBeautifier($dir);
if (is_dir($dir)) {
self::debug("-> Ordner existiert bereits: '$dir'");
return $dir;
}
if (mkdir($dir, $permission, true)) {
chmod($dir, $permission);
$result = $dir . '/';
self::debug("-> Ordner erstellt: '$result'");
return $result;
}
$error = error_get_last();
$msg = "Verzeichnis konnte nicht erstellt werden: $dir - " . ($error['message'] ?? 'unbekannter Fehler');
self::debug($msg);
return false;
}
public static function deleteFile(string $file, bool $throwOnError = false): bool
{
if (!file_exists($file)) {
return true;
}
if (unlink($file)) {
self::debug("Datei gelöscht: $file");
return true;
}
$error = error_get_last();
$msg = "Datei konnte nicht gelöscht werden: $file - " . ($error['message'] ?? 'unbekannter Fehler');
self::debug($msg);
if ($throwOnError) {
throw new RuntimeException($msg);
}
return false;
}
// Instanzmethoden für Dependency Injection (rufen die statischen Methoden auf)
public function createDirInstance(string $dir, int $permission = 0777)
{
return self::createDir($dir, $permission);
}
public function deleteFileInstance(string $file, bool $throwOnError = false): bool
{
return self::deleteFile($file, $throwOnError);
}
public function eraseInstance(string $dir_file): void
{
self::erase($dir_file);
}
public function clearCacheElemsByDirInstance(string $dir): bool
{
return self::clearCacheElemsByDir($dir);
}
public function clearCacheElemByLocationInstance(string $loc): bool
{
return self::clearCacheElemByLocation($loc);
}
########################### STRUKTUR-CACHE
private static function getStructureCache(): FV_StructureCache
{
if (self::$structureCache === null) {
$cacheJsonDir = FV_Core::cacheDir() . 'json/';
self::$structureCache = new FV_StructureCache(
$cacheJsonDir,
FV_Core::scanDir(),
FV_Core::$enableStructureCache,
function ($dir, $detail) {
return self::readDirInfo($dir, $detail);
},
new self() // Instanz für Dependency Injection übergeben
);
}
return self::$structureCache;
}
private static function getCachedStructureInfo(string $realDir, int $detail)
{
self::debug("getCachedStructureInfo: realDir='$realDir', detail=$detail");
return self::getStructureCache()->get($realDir, $detail);
}
private static function saveStructureCache(string $realDir, int $detail, array $data, ?string $snapshotHash = null): bool
{
self::debug("saveStructureCache: realDir='$realDir', detail=$detail");
return self::getStructureCache()->set($realDir, $detail, $data, $snapshotHash);
}
private static function clearStructureCache(string $path): void
{
self::debug("clearStructureCache: path='$path'");
self::getStructureCache()->clear($path);
}
########################### CACHE-LÖSCHUNG
public static function clearCacheElemsByDir(string $dir): bool
{
self::debug("clearCacheElemsByDir: dir='$dir'");
$return = false;
$dirList = self::readDirInfo($dir, 0);
if (!empty($dirList['fileList'])) {
foreach ($dirList['fileList'] as $info) {
self::clearCacheElemByLocation($info['location']);
$return = true;
}
}
return $return;
}
public static function clearCacheElemByLocation(string $loc): bool
{
self::debug("clearCacheElemByLocation: loc='$loc'");
$loc = FV_Helper::slashBeautifier($loc);
$return = false;
$hash = md5($loc);
$dir = substr($hash, 0, 1);
$ident = 'c_' . crc32($loc) . '_';
$cacheDir = FV_Core::cacheDir() . $dir;
$dirList = self::readDirInfo($cacheDir, 0);
if (!empty($dirList['fileList'])) {
foreach ($dirList['fileList'] as $info) {
if (str_starts_with($info['name'], $ident)) {
self::deleteFile($info['location'], false);
$return = true;
self::debug("-> Cache-Element gelöscht: " . $info['name']);
}
}
}
self::clearStructureCache($loc);
return $return;
}
########################### BILD-INFO-CACHE
private static function getImageInfoWithCache(string $file, int $detail): array
{
self::debug("getImageInfoWithCache: file='$file', detail=$detail");
$cacheKey = md5($file . '_' . $detail);
if (isset(self::$imageInfoCache[$cacheKey])) {
self::debug("-> Cache-Treffer");
return self::$imageInfoCache[$cacheKey];
}
$result = ['image' => null, 'exif' => null];
//-- Bildgröße ermitteln (ohne @, mit zentralem Logging)
$img = getimagesize($file);
if ($img === false) {
self::debug("getimagesize fehlgeschlagen für $file");
return $result;
}
$x = $img[0];
$y = $img[1];
$pxs = $x * $y;
$result['image'] = [
'sizeX'          => $x,
'sizeY'          => $y,
'pixels'         => $pxs,
'pixelsFormated' => ($pxs < 100000) ? number_format($pxs / 1e6, 2, ',', '.') . ' MP' : number_format($pxs / 1e6, 1, ',', '.') . ' MP',
'bits'           => $img['bits'] ?? false,
'channels'       => $img['channels'] ?? false,
'extension'      => self::$intToMime[$img[2]] ?? false,
];
if ($detail >= 2 && in_array($img[2], [2, 7]) && function_exists('exif_read_data')) {
$result['exif'] = FV_Image::getExifInfo($file);
}
self::$imageInfoCache[$cacheKey] = $result;
self::debug("-> Bild-Info gelesen und gecacht");
return $result;
}
########################### HAUPT-METHODE: ORDNER AUSLESEN
public static function readDirInfo(string $dir, int $detail = 1)
{
self::debug("========== readDirInfo START ==========");
self::debug("Pfad: " . $dir);
self::debug("Detail: " . $detail);
$realDir = FV_Helper::slashBeautifier(realpath((string) $dir));
if (!$realDir || !is_dir($realDir)) {
self::debug("-> KEIN gültiges Verzeichnis");
return false;
}
$realDir = rtrim($realDir, '/') . '/';
$dirName = basename(rtrim($realDir, '/'));
if (self::isIgnoredItem($dirName, $realDir)) {
self::debug("-> IGNORIERT: " . $dirName);
return false;
}
$cacheDirReal = rtrim(FV_Core::cacheDir(), '/') . '/';
$isInsideCache = self::isInsideCacheDir($realDir);
self::debug("isInsideCache: " . ($isInsideCache ? 'JA' : 'NEIN'));
if (FV_Core::$enableStructureCache && !$isInsideCache) {
$cached = self::getCachedStructureInfo($realDir, $detail);
if ($cached !== false) {
self::debug("-> CACHE-TREFFER!");
return $cached;
}
}
$dirList = [];
$fileList = [];
$totalSize = 0;
$totalCount = 0;
$fileBytes = 0;
$hasImages = false;
$imgExtensions = FV_Helper::softImplode(FV_Helper::mimeInfoFilter(['type' => 'image']), 'extension');
try {
$iterator = new FilesystemIterator($realDir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS);
} catch (UnexpectedValueException $e) {
self::debug("FEHLER: Kann Ordner nicht lesen: " . $e->getMessage());
return false;
}
foreach ($iterator as $item) {
$loc = $item->getPathname();
$name = $item->getFilename();
if (self::isIgnoredItem($name, $loc)) continue;
if (!$isInsideCache && self::isInsideCacheDir($loc . (is_dir($loc) ? '/' : ''))) {
self::debug("  Ignoriere Cache-Item (nicht im Cache): " . $name);
continue;
}
if ($item->isDir()) {
self::debug("  Verarbeite Ordner: " . $name);
$sub = self::readDirInfo($loc . '/', 0);
if ($sub) {
$totalSize += $sub['totalSize'];
$totalCount += $sub['totalCount'];
$dirList[] = [
'name'                => $name,
'location'            => $loc . '/',
'parentDir'           => $realDir,
'dirFileCount'        => $sub['totalCount'],
'dirFileSize'         => $sub['totalSize'],
'dirFileSizeFormated' => FV_Helper::formatByte($sub['totalSize']),
'isCacheDir'          => false,
];
}
} elseif ($item->isFile()) {
self::debug("  Verarbeite Datei: " . $name);
$size = $item->getSize();
$mtime = $item->getMTime();
$totalSize += $size;
$totalCount++;
$fileBytes += $size;
$extension = $item->getExtension();
if (in_array(strtolower($extension), $imgExtensions)) {
$hasImages = true;
}
$fileList[] = [
'location'         => $loc,
'parentDir'        => $realDir,
'name'             => pathinfo($name, PATHINFO_FILENAME),
'extension'        => $extension,
'fileName'         => $name,
'fileTime'         => $mtime,
'fileTimeFormated' => FV_Helper::formatDate($mtime),
'fileSize'         => $size,
'fileSizeFormated' => FV_Helper::formatByte($size),
'mimeInfoFilter'   => FV_Helper::mimeInfoFilter(['extension' => $extension])[0] ?? [],
];
if ($detail >= 1) {
if (in_array(strtolower($extension), $imgExtensions)) {
$imgInfo = self::getImageInfoWithCache($loc, $detail);
if (!empty($imgInfo)) {
$fileList[count($fileList) - 1]['image'] = $imgInfo['image'] ?? null;
$fileList[count($fileList) - 1]['exif'] = $imgInfo['exif'] ?? null;
}
}
}
}
}
$asGoodAsDetail = $detail;
if (!$hasImages) {
$asGoodAsDetail = 2;
}
$result = [
'totalSize'    => $totalSize,
'totalCount'   => $totalCount,
'dirList'      => $dirList,
'fileList'     => $fileList,
'dirListInfo'  => ['count' => count($dirList)],
'fileListInfo' => [
'count'    => count($fileList),
'fileSize' => $fileBytes,
'fileSizeFormated' => FV_Helper::formatByte($fileBytes),
],
'asGoodAsDetail' => $asGoodAsDetail,
];
self::debug("Ergebnis: " . count($dirList) . " Ordner, " . count($fileList) . " Dateien");
self::debug("asGoodAsDetail: " . $asGoodAsDetail);
if (FV_Core::$enableStructureCache && !$isInsideCache) {
self::saveStructureCache($realDir, $detail, $result, self::calcStructureSnapshotFromResult($result));
} else {
self::debug("-> Cache speichern übersprungen (im Cache-Baum)");
}
self::debug("========== readDirInfo ENDE ==========");
return $result;
}
private static function calcStructureSnapshotFromResult(array $result): string
{
$items = [];
foreach ($result['dirList'] as $dir) {
$items[] = 'D:' . $dir['name'] . '|' . ($dir['dirFileCount'] ?? 0) . '|' . ($dir['dirFileSize'] ?? 0);
}
foreach ($result['fileList'] as $file) {
$items[] = 'F:' . $file['fileName'] . '|' . $file['fileSize'] . '|' . $file['fileTime'];
}
sort($items);
return hash('sha256', implode('', $items));
}
########################### DATEI-INFORMATIONEN
public static function readFileInfo(string $file, int $detail = 0)
{
self::debug("readFileInfo ENTRY: file='$file', detail=$detail");
$realFile = FV_Helper::slashBeautifier(realpath((string) $file));
if (!$realFile || is_dir($realFile) || !file_exists($realFile)) {
self::debug("-> KEINE gültige Datei");
return false;
}
$pi = FV_Core::pathInfo($realFile);
$size = filesize($realFile);
$mtime = filemtime($realFile);
$info = [
'location'         => $realFile,
'parentDir'        => ($pi['dirname'] ? rtrim($pi['dirname'], '/') . '/' : false),
'name'             => $pi['filename'] ?: false,
'extension'        => $pi['extension'] ?: false,
'fileName'         => $pi['basename'] ?: false,
'fileTime'         => $mtime,
'fileTimeFormated' => FV_Helper::formatDate($mtime),
'fileSize'         => $size,
'fileSizeFormated' => FV_Helper::formatByte($size),
'image'            => null,
'exif'             => null,
];
if ($detail >= 1) {
$imgTypes = FV_Helper::softImplode(FV_Helper::mimeInfoFilter(['type' => 'image']), 'extension');
if (in_array(strtolower($info['extension'] ?? ''), $imgTypes) && ($img = @getimagesize($realFile))) {
$x = $img[0];
$y = $img[1];
$pxs = $x * $y;
$info['image'] = [
'sizeX'          => $x,
'sizeY'          => $y,
'pixels'         => $pxs,
'pixelsFormated' => ($pxs < 100000) ? number_format($pxs / 1e6, 2, ',', '.') . ' MP' : number_format($pxs / 1e6, 1, ',', '.') . ' MP',
'bits'           => $img['bits'] ?? false,
'channels'       => $img['channels'] ?? false,
'extension'      => self::$intToMime[$img[2]] ?? false,
];
if ($detail >= 2 && in_array($img[2], [2, 7]) && function_exists('exif_read_data')) {
$info['exif'] = FV_Image::getExifInfo($realFile);
}
}
}
$info['mimeInfoFilter'] = [];
if (!empty($pi['extension'])) {
$filtered = FV_Helper::mimeInfoFilter(['extension' => $pi['extension']]);
$info['mimeInfoFilter'] = $filtered[0] ?? [];
}
self::debug("-> Datei-Info zurückgegeben");
return $info;
}
}
?>
<?php
/**
* FV_UI – Benutzeroberflächen-Hilfsklasse für E2 FileViewer
* ======================================================
*
* Diese Klasse stellt alle Funktionen für die Darstellung der
* Benutzeroberfläche bereit. Sie generiert HTML-Elemente für Ordner,
* Dateien, Cache-Ordner und Informationszeilen.
*
*
* KERN-FUNKTIONEN
* ---------------
* - Rendering von Ordner- und Dateilisten
* - Icons für verschiedene Dateitypen (Font-Awesome-basiert)
* - Vorschaubilder für Bilder (Thumbnails)
* - EXIF-Daten-Anzeige für Fotografen-Ansicht
* - Sortierung der Listenelemente
* - Cache-Ordner spezielle Darstellung
*
*
* ANSICHTEN (VIEWS)
* -----------------
* - auto: Automatische Ansicht (Thumbnails bei Bildern)
* - consumer: Endkunden-Ansicht (einfache Darstellung)
* - photographer: Fotografen-Ansicht (mit EXIF-Daten)
* - it: IT-Ansicht (nur Basis-Informationen)
*
*
* SORTIERUNG
* ----------
* - name: Nach Dateinamen (a=aufsteigend, z=absteigend)
* - size: Nach Dateigröße
* - date: Nach Änderungsdatum
* - type: Nach Dateityp (Erweiterung)
*
*
* ICON-MAPPING
* ------------
* Über 400 Font-Icons aus Font Awesome 4.7 werden über data-ic-Attribute
* eingebunden. Die Zuordnung erfolgt über die icArr()-Methode.
*
*
* ABHÄNGIGKEITEN
* --------------
* - FV_FileSystem: Für Dateisystemabfragen (isExtensionHidden, isCacheFolderVisibleInPath, readDirInfo)
*   Wird über den optionalen Parameter $fileSystem in buildListItems() injiziert.
*
*
* VERWENDUNG
* ----------
*   $items = FV_UI::buildListItems($readedList, $options);
*   echo FV_UI::renderUl($items['listItems']);
*
* =====================================================================
*/
class FV_UI
{
public const VIEW_AUTO         = 'auto';
public const VIEW_CONSUMER     = 'consumer';
public const VIEW_PHOTOGRAPHER = 'photographer';
public const VIEW_IT           = 'it';
public const SORT_BY_NAME      = 'name';
public const SORT_BY_SIZE      = 'size';
public const SORT_BY_DATE      = 'date';
public const SORT_BY_TYPE      = 'type';
########################### DEBUG-HELFER
private static function debug($message): void
{
if (FV_Core::isDebugEnabled('fv_ui')) {
error_log("[FV_UI] " . $message);
}
}
########################### ICON-MAPPING
##### GIBT DIE VOLLSTÄNDIGE ICON-MAPPING-TABELLE ZURÜCK (FA 4.7)
public static function icArr(): array
{
return [
'adjust'               => 'f042',
'adn'                  => 'f170',
'align-center'         => 'f037',
'align-justify'        => 'f039',
'align-left'           => 'f036',
'align-right'          => 'f038',
'ambulance'            => 'f0f9',
'anchor'               => 'f13d',
'android'              => 'f17b',
'angle-double-down'    => 'f103',
'angle-double-left'    => 'f100',
'angle-double-right'   => 'f101',
'angle-double-up'      => 'f102',
'angle-down'           => 'f107',
'angle-left'           => 'f104',
'angle-right'          => 'f105',
'angle-up'             => 'f106',
'apple'                => 'f179',
'archive'              => 'f187',
'arrow-circle-down'    => 'f0ab',
'arrow-circle-left'    => 'f0a8',
'arrow-circle-o-down'  => 'f01a',
'arrow-circle-o-left'  => 'f190',
'arrow-circle-o-right' => 'f18e',
'arrow-circle-o-up'    => 'f01a',
'arrow-circle-right'   => 'f0a9',
'arrow-circle-up'      => 'f0aa',
'arrow-down'           => 'f063',
'arrow-left'           => 'f060',
'arrow-right'          => 'f061',
'arrow-up'             => 'f062',
'arrows'               => 'f047',
'arrows-alt'           => 'f0b2',
'arrows-h'             => 'f07e',
'arrows-v'             => 'f07d',
'asterisk'             => 'f069',
'backward'             => 'f04a',
'ban'                  => 'f05e',
'bar-chart-o'          => 'f080',
'barcode'              => 'f02a',
'bars'                 => 'f0c9',
'beer'                 => 'f0fc',
'bell'                 => 'f0f3',
'bell-o'               => 'f0a2',
'bitbucket'            => 'f171',
'bitbucket-square'     => 'f172',
'bold'                 => 'f032',
'book'                 => 'f02d',
'bookmark'             => 'f02e',
'bookmark-o'           => 'f097',
'briefcase'            => 'f0b1',
'btc'                  => 'f15a',
'bug'                  => 'f188',
'building-o'           => 'f0f7',
'bullhorn'             => 'f0a1',
'bullseye'             => 'f140',
'calendar'             => 'f073',
'calendar-o'           => 'f133',
'camera'               => 'f030',
'camera-retro'         => 'f083',
'caret-down'           => 'f0d7',
'caret-left'           => 'f0d9',
'caret-right'          => 'f0da',
'caret-square-o-down'  => 'f150',
'caret-square-o-left'  => 'f191',
'caret-square-o-right' => 'f152',
'caret-square-o-up'    => 'f151',
'caret-up'             => 'f0d8',
'certificate'          => 'f0a3',
'check'                => 'f00c',
'check-circle'         => 'f058',
'check-circle-o'       => 'f05d',
'check-square'         => 'f14a',
'check-square-o'       => 'f046',
'chevron-circle-down'  => 'f13a',
'chevron-circle-left'  => 'f137',
'chevron-circle-right' => 'f138',
'chevron-circle-up'    => 'f139',
'chevron-down'         => 'f078',
'chevron-left'         => 'f053',
'chevron-right'        => 'f054',
'chevron-up'           => 'f077',
'circle'               => 'f111',
'circle-o'             => 'f10c',
'clock-o'              => 'f017',
'cloud'                => 'f0c2',
'cloud-download'       => 'f0ed',
'cloud-upload'         => 'f0ee',
'code'                 => 'f121',
'code-fork'            => 'f126',
'coffee'               => 'f0f4',
'columns'              => 'f0db',
'comment'              => 'f075',
'comment-o'            => 'f0e5',
'comments'             => 'f086',
'comments-o'           => 'f0e6',
'compass'              => 'f14e',
'compress'             => 'f066',
'credit-card'          => 'f09d',
'crop'                 => 'f125',
'crosshairs'           => 'f05b',
'css3'                 => 'f13c',
'cut'                  => 'f0c4',
'cutlery'              => 'f0f5',
'desktop'              => 'f108',
'dot-circle-o'         => 'f192',
'download'             => 'f019',
'dribbble'             => 'f17d',
'dropbox'              => 'f16b',
'eject'                => 'f052',
'ellipsis-h'           => 'f141',
'ellipsis-v'           => 'f142',
'envelope'             => 'f0e0',
'envelope-o'           => 'f003',
'eraser'               => 'f12d',
'eur'                  => 'f153',
'exchange'             => 'f0ec',
'exclamation'          => 'f12a',
'exclamation-circle'   => 'f06a',
'expand'               => 'f065',
'external-link'        => 'f08e',
'external-link-square' => 'f14c',
'eye'                  => 'f06e',
'eye-slash'            => 'f070',
'facebook'             => 'f09a',
'facebook-square'      => 'f082',
'fast-backward'        => 'f049',
'fast-forward'         => 'f050',
'female'               => 'f182',
'fighter-jet'          => 'f0fb',
'file'                 => 'f15b',
'file-o'               => 'f016',
'file-text'            => 'f15c',
'file-text-o'          => 'f0f6',
'files-o'              => 'f0c5',
'film'                 => 'f008',
'filter'               => 'f0b0',
'fire'                 => 'f06d',
'fire-extinguisher'    => 'f134',
'flag'                 => 'f024',
'flag-checkered'       => 'f11e',
'flag-o'               => 'f11d',
'flash'                => 'f0e7',
'flask'                => 'f0c3',
'flickr'               => 'f16e',
'folder'               => 'f07b',
'folder-o'             => 'f114',
'folder-open'          => 'f07c',
'folder-open-o'        => 'f115',
'font'                 => 'f031',
'forward'              => 'f04e',
'foursquare'           => 'f180',
'frown-o'              => 'f119',
'gamepad'              => 'f11b',
'gavel'                => 'f0e3',
'gbp'                  => 'f154',
'gear'                 => 'f013',
'gears'                => 'f085',
'gift'                 => 'f06b',
'github'               => 'f09b',
'github-alt'           => 'f113',
'github-square'        => 'f092',
'gittip'               => 'f184',
'glass'                => 'f000',
'globe'                => 'f0ac',
'google-plus'          => 'f0d5',
'google-plus-square'   => 'f0d4',
'group'                => 'f0c0',
'h-square'             => 'f0fd',
'hand-o-down'          => 'f0a7',
'hand-o-left'          => 'f0a5',
'hand-o-right'         => 'f0a4',
'hand-o-up'            => 'f0a6',
'hdd-o'                => 'f0a0',
'headphones'           => 'f025',
'heart'                => 'f004',
'heart-o'              => 'f08a',
'home'                 => 'f015',
'hospital-o'           => 'f0f8',
'html5'                => 'f13b',
'inbox'                => 'f01c',
'indent'               => 'f03c',
'info'                 => 'f129',
'info-circle'          => 'f05a',
'inr'                  => 'f156',
'instagram'            => 'f16d',
'italic'               => 'f033',
'jpy'                  => 'f157',
'key'                  => 'f084',
'keyboard-o'           => 'f11c',
'krw'                  => 'f159',
'laptop'               => 'f109',
'leaf'                 => 'f06c',
'lemon-o'              => 'f094',
'level-down'           => 'f149',
'level-up'             => 'f148',
'lightbulb-o'          => 'f0eb',
'link'                 => 'f0c1',
'linkedin'             => 'f0e1',
'linkedin-square'      => 'f08c',
'linux'                => 'f17c',
'list'                 => 'f03a',
'list-alt'             => 'f022',
'list-ol'              => 'f0cb',
'list-ul'              => 'f0ca',
'location-arrow'       => 'f124',
'lock'                 => 'f023',
'long-arrow-down'      => 'f175',
'long-arrow-left'      => 'f177',
'long-arrow-right'     => 'f178',
'long-arrow-up'        => 'f176',
'magic'                => 'f0d0',
'magnet'               => 'f076',
'mail-reply-all'       => 'f122',
'male'                 => 'f183',
'map-marker'           => 'f041',
'maxcdn'               => 'f136',
'medkit'               => 'f0fa',
'meh-o'                => 'f11a',
'microphone'           => 'f130',
'microphone-slash'     => 'f131',
'minus'                => 'f068',
'minus-circle'         => 'f056',
'minus-square'         => 'f146',
'minus-square-o'       => 'f147',
'mobile'               => 'f10b',
'money'                => 'f0d6',
'moon-o'               => 'f186',
'music'                => 'f001',
'outdent'              => 'f03b',
'pagelines'            => 'f18c',
'paperclip'            => 'f0c6',
'paste'                => 'f0ea',
'pause'                => 'f04c',
'pencil'               => 'f040',
'pencil-square'        => 'f14b',
'pencil-square-o'      => 'f044',
'phone'                => 'f095',
'phone-square'         => 'f098',
'picture-o'            => 'f03e',
'pinterest'            => 'f0d2',
'pinterest-square'     => 'f0d3',
'plane'                => 'f072',
'play'                 => 'f04b',
'play-circle'          => 'f144',
'play-circle-o'        => 'f01d',
'plus'                 => 'f067',
'plus-circle'          => 'f055',
'plus-square'          => 'f0fe',
'power-off'            => 'f011',
'print'                => 'f02f',
'puzzle-piece'         => 'f12e',
'qrcode'               => 'f029',
'question'             => 'f128',
'question-circle'      => 'f059',
'quote-left'           => 'f10d',
'quote-right'          => 'f10e',
'random'               => 'f074',
'refresh'              => 'f021',
'renren'               => 'f18b',
'repeat'               => 'f01e',
'reply'                => 'f112',
'reply-all'            => 'f122',
'retweet'              => 'f079',
'road'                 => 'f018',
'rocket'               => 'f135',
'rss'                  => 'f09e',
'rss-square'           => 'f143',
'rub'                  => 'f158',
'save'                 => 'f0c7',
'search'               => 'f002',
'search-minus'         => 'f010',
'search-plus'          => 'f00e',
'share'                => 'f064',
'share-square'         => 'f14d',
'share-square-o'       => 'f045',
'shield'               => 'f132',
'shopping-cart'        => 'f07a',
'sign-in'              => 'f090',
'sign-out'             => 'f08b',
'signal'               => 'f012',
'sitemap'              => 'f0e8',
'skype'                => 'f17e',
'smile-o'              => 'f118',
'sort'                 => 'f0dc',
'sort-alpha-asc'       => 'f15d',
'sort-alpha-desc'      => 'f15e',
'sort-amount-asc'      => 'f160',
'sort-amount-desc'     => 'f161',
'sort-asc'             => 'f0dd',
'sort-desc'            => 'f0de',
'sort-numeric-asc'     => 'f162',
'sort-numeric-desc'    => 'f163',
'spinner'              => 'f110',
'square'               => 'f0c8',
'square-o'             => 'f096',
'stack-exchange'       => 'f18d',
'stack-overflow'       => 'f16c',
'star'                 => 'f005',
'star-half'            => 'f089',
'star-half-o'          => 'f123',
'star-o'               => 'f006',
'step-backward'        => 'f048',
'step-forward'         => 'f051',
'stethoscope'          => 'f0f1',
'stop'                 => 'f04d',
'strikethrough'        => 'f0cc',
'subscript'            => 'f12c',
'suitcase'             => 'f0f2',
'sun-o'                => 'f185',
'superscript'          => 'f12b',
'table'                => 'f0ce',
'tablet'               => 'f10a',
'tachometer'           => 'f0e4',
'tag'                  => 'f02b',
'tags'                 => 'f02c',
'tasks'                => 'f0ae',
'terminal'             => 'f120',
'text-height'          => 'f034',
'text-width'           => 'f035',
'th'                   => 'f00a',
'th-large'             => 'f009',
'th-list'              => 'f00b',
'thumb-tack'           => 'f08d',
'thumbs-down'          => 'f165',
'thumbs-o-down'        => 'f088',
'thumbs-o-up'          => 'f087',
'thumbs-up'            => 'f164',
'ticket'               => 'f145',
'times'                => 'f00d',
'times-circle'         => 'f057',
'times-circle-o'       => 'f05c',
'tint'                 => 'f043',
'trash-o'              => 'f014',
'trello'               => 'f181',
'trophy'               => 'f091',
'truck'                => 'f0d1',
'try'                  => 'f195',
'tumblr'               => 'f173',
'tumblr-square'        => 'f174',
'twitter'              => 'f099',
'twitter-square'       => 'f081',
'umbrella'             => 'f0e9',
'underline'            => 'f0cd',
'undo'                 => 'f0e2',
'unlink'               => 'f127',
'unlock'               => 'f09c',
'unlock-alt'           => 'f13e',
'upload'               => 'f093',
'usd'                  => 'f155',
'user'                 => 'f007',
'user-md'              => 'f0f0',
'video-camera'         => 'f03d',
'vimeo-square'         => 'f194',
'vk'                   => 'f189',
'volume-down'          => 'f027',
'volume-off'           => 'f026',
'volume-up'            => 'f028',
'warning'              => 'f071',
'weibo'                => 'f18a',
'wheelchair'           => 'f193',
'windows'              => 'f17a',
'wrench'               => 'f0ad',
'xing'                 => 'f168',
'xing-square'          => 'f169',
'youtube'              => 'f167',
'youtube-play'         => 'f16a',
'youtube-square'       => 'f166'
];
}
########################### LISTEN-STRUKTUR
##### RENDERT EINE UNORDERED LIST AUS GEGEBENEN ITEMS
public static function renderUl(array $items): string
{
if (empty($items)) return '';
return '<ul class="elist" role="main" data-elayer-group>' . implode('', $items) . '</ul>';
}
##### BAUT DIE VOLLSTÄNDIGE LISTE AUS ORDNERN UND DATEIEN
public static function buildListItems(array $readedList, array $options, ?FV_FileSystem $fileSystem = null): array
{
self::debug("========== buildListItems START ==========");
$listItems = [];
$currentPath            = $options['currentPath'] ?? '';
$sortBy                 = $options['sortBy'] ?? self::SORT_BY_NAME;
$sortDir                = $options['sortDir'] ?? 'a';
$view                   = $options['view'] ?? self::VIEW_AUTO;
$showCacheFolder        = $options['showCacheFolder'] ?? true;
$showHideExtensionsInfo = $options['showHideExtensionsInfo'] ?? true;
self::debug("currentPath: " . $currentPath);
self::debug("sortBy: " . $sortBy);
self::debug("sortDir: " . $sortDir);
self::debug("view: " . $view);
self::debug("showCacheFolder: " . ($showCacheFolder ? 'JA' : 'NEIN'));
self::debug("showHideExtensionsInfo: " . ($showHideExtensionsInfo ? 'JA' : 'NEIN'));
self::debug("readedList dirList count: " . count($readedList['dirList'] ?? []));
self::debug("readedList fileList count: " . count($readedList['fileList'] ?? []));
$stats = [
'biggestDir'        => 0.0001,
'biggestFile'       => 0.0001,
'folderCount'       => 0,
'folderFileCount'   => 0,
'folderFileSize'    => 0,
'visibleFileCount'  => 0,
'visibleFileSize'   => 0,
'totalFileCount'    => 0,
'totalFileSize'     => 0,
'hiddenFileCount'   => 0,
'hiddenFileSize'    => 0
];
//-- 1. ORDNER DURCHLAUFEN (aus $readedList, ohne Cache-Ordner)
$dirSortList = [];
$dirList = [];
if (!empty($readedList['dirList']) && count($readedList['dirList']) >= 1) {
self::debug("Verarbeite " . count($readedList['dirList']) . " Ordner aus readedList");
$ie = 0;
foreach ($readedList['dirList'] as $thisE) {
$ie++;
self::debug("  Ordner: " . $thisE['name']);
$stats['folderCount']++;
$stats['folderFileCount'] += $thisE['dirFileCount'];
$stats['folderFileSize']  += $thisE['dirFileSize'];
if ($thisE['dirFileSize'] > $stats['biggestDir']) {
$stats['biggestDir'] = $thisE['dirFileSize'];
}
$dirSortList[self::SORT_BY_DATE][$ie] = $thisE['name'];
$dirSortList[self::SORT_BY_SIZE][$ie] = $thisE['dirFileSize'];
$dirSortList[self::SORT_BY_NAME][$ie] = $thisE['name'];
$dirSortList[self::SORT_BY_TYPE][$ie] = $thisE['name'];
$dirList[$ie] = [
'target'        => FV_Core::insPath(true) . '?fvd=' . urlencode($currentPath . $thisE['name']),
'lebo'          => $thisE['dirFileCount'] . ' ' . FV_Lang::get('files'),
'ribo'          => $thisE['dirFileSizeFormated'],
'name'          => $thisE['name'],
'size'          => $thisE['dirFileSize'],
'checkboxValue' => $currentPath . $thisE['name']
];
}
asort($dirSortList[$sortBy]);
if ($sortDir == 'z') {
$dirSortList[$sortBy] = array_reverse($dirSortList[$sortBy], true);
}
} else {
self::debug("Keine Ordner in readedList");
}
//-- 2. NORMALE ORDNER RENDERN
if (!empty($dirList)) {
self::debug("Rendere " . count($dirList) . " normale Ordner");
foreach ($dirSortList[$sortBy] as $key => $val) {
if (isset($dirList[$key])) {
$EL = $dirList[$key];
$listItems[] = self::renderFolder([
'target'        => $EL['target'],
'name'          => $EL['name'],
'lebo'          => $EL['lebo'],
'ribo'          => $EL['ribo'],
'size'          => $EL['size'],
'maxSize'       => $stats['biggestDir'],
'checkboxValue' => $EL['checkboxValue']
]);
}
}
}
//-- 3. CACHE-ORDNER SEPARAT NACHLADEN
self::debug("Prüfe Cache-Ordner-Nachladung: showCacheFolder=" . ($showCacheFolder ? 'JA' : 'NEIN') . ", currentPath='$currentPath'");
$fs = $fileSystem ?? new FV_FileSystem();
$cacheVisible = $fs->isCacheFolderVisibleInPath($currentPath);
self::debug("isCacheFolderVisibleInPath: " . ($cacheVisible ? 'JA' : 'NEIN'));
if ($showCacheFolder && $cacheVisible) {
self::debug("Lade Cache-Ordner separat nach...");
$cacheDirReal = FV_Core::cacheDir();
self::debug("Cache-Ordner Pfad: " . $cacheDirReal);
$cacheDirInfo = $fs->readDirInfo($cacheDirReal, 0);
self::debug("Cache-Ordner readDirInfo Ergebnis: totalSize=" . ($cacheDirInfo['totalSize'] ?? 0));
self::debug("Cache-Ordner dirList count=" . count($cacheDirInfo['dirList'] ?? []));
self::debug("Cache-Ordner fileList count=" . count($cacheDirInfo['fileList'] ?? []));
$liCacheDir = self::renderCacheFolder([
'target'  => FV_Core::insPath(true) . '?fvd=' . urlencode(basename($cacheDirReal)),
'ribo'    => FV_Helper::formatByte($cacheDirInfo['totalSize'] ?? 0),
'size'    => $cacheDirInfo['totalSize'] ?? 0,
'maxSize' => max($stats['biggestDir'], 1)
]);
if (!empty($liCacheDir)) {
self::debug("Cache-Ordner wird zur Liste hinzugefügt");
$listItems[] = $liCacheDir;
} else {
self::debug("renderCacheFolder gab leeren String zurück");
}
} else {
self::debug("Cache-Ordner wird NICHT nachgeladen: showCacheFolder=" . ($showCacheFolder ? 'JA' : 'NEIN') . ", visible=" . ($cacheVisible ? 'JA' : 'NEIN'));
}
//-- 4. DATEIEN DURCHLAUFEN
$fileSortList = [];
$fileList = [];
if (!empty($readedList['fileList']) && count($readedList['fileList']) >= 1) {
self::debug("Verarbeite " . count($readedList['fileList']) . " Dateien aus readedList");
$ie = 0;
foreach ($readedList['fileList'] as $thisE) {
$ie++;
if (in_array($thisE['location'], [
FV_Core::insDir() . FV_Core::$insFilename,
FV_Core::insDir() . FV_Core::$cfgFilename
])) {
self::debug("  Überspringe Systemdatei: " . $thisE['fileName']);
continue;
}
if ($thisE['fileSize'] > $stats['biggestFile']) {
$stats['biggestFile'] = $thisE['fileSize'];
}
if (FV_Core::$readInMode) {
$targetOrg = $target = FV_Core::insPath(true) . '?fvf=' . urlencode($currentPath . $thisE['fileName']);
} else {
$targetOrg = $target = FV_Core::scanPath() . $currentPath . $thisE['fileName'];
}
$targetViewer = $currentPath . $thisE['fileName'];
$lebo         = date('Y-m-d', $thisE['fileTime']) . '<span class="micro">&nbsp;' . date('H:i', $thisE['fileTime']) . '</span>';
$thumbInfo    = false;
$thumbImage   = false;
$thumbSharp   = false;
$ribo         = $thisE['fileSizeFormated'];
$image = $thisE['image'] ?? null;
$exif  = $thisE['exif'] ?? null;
if (!empty($image) && in_array($view, [self::VIEW_AUTO, self::VIEW_CONSUMER, self::VIEW_PHOTOGRAPHER])) {
$micro = $image['sizeX'] . 'x' . $image['sizeY'];
if ($view == self::VIEW_PHOTOGRAPHER && $image['pixels'] > 600000) {
$micro = str_replace('.0', '', number_format($image['pixels'] / 1000000, 1)) . 'MP<br />' . $micro;
}
$ribo = '<div class="micro">' . $micro . '</div>' . $ribo;
$target = FV_Core::insPath(true) . '?size=' . FV_Core::$imagePixels . '&modus=l&fvi=' . urlencode($targetViewer) . '&ex=.' . $image['extension'];
$thumbImage = FV_Core::insPath(true) . '?size=' . FV_Core::$thumbPixels . '&modus=q&webp=true&fvi=' . urlencode($targetViewer);
if ($image['pixels'] < (80 * 80)) {
$thumbSharp = true;
}
if ($view == self::VIEW_PHOTOGRAPHER && !empty($exif) && !empty($exif['iso'])) {
$thumbInfo = '' .
($exif['exposureTime'] ?? '?') . 's' .
'<br />ISO ' . ($exif['iso'] ?? '?') .
'<br />F ' . ($exif['fNumber'] ?? '?') .
'<br />' . ($exif['camModel'] ?? '');
}
if (!empty($exif) && !empty($exif['dateTime'])) {
$lebo = date('Y-m-d', $exif['dateTime']) . '<span class="micro">&nbsp;' . date('H:i', $exif['dateTime']) . '</span>';
}
}
$fileSortList[self::SORT_BY_DATE][$ie] = $thisE['fileTime'];
$fileSortList[self::SORT_BY_SIZE][$ie] = $thisE['fileSize'];
$fileSortList[self::SORT_BY_NAME][$ie] = $thisE['name'];
$fileSortList[self::SORT_BY_TYPE][$ie] = $thisE['extension'];
$fileList[$ie] = [
'target'        => $target,
'targetOrg'     => $targetOrg,
'thumbInfo'     => $thumbInfo,
'thumbImage'    => $thumbImage,
'thumbSharp'    => $thumbSharp,
'ext'           => $thisE['extension'],
'lebo'          => $lebo,
'ribo'          => $ribo,
'name'          => $thisE['name'],
'fileName'      => $thisE['fileName'],
'fileLocation'  => $thisE['location'],
'size'          => $thisE['fileSize'],
'checkboxValue' => $currentPath . $thisE['fileName'],
'downloadName'  => $thisE['fileName']
];
}
asort($fileSortList[$sortBy]);
if ($sortDir == 'z') {
$fileSortList[$sortBy] = array_reverse($fileSortList[$sortBy], true);
}
}
//-- 5. DATEIEN RENDERN
if (!empty($fileList)) {
self::debug("Rendere Dateien...");
foreach ($fileSortList[$sortBy] as $key => $val) {
if (isset($fileList[$key])) {
$EL = $fileList[$key];
if (!$fs->isExtensionHidden($EL['ext'])) {
$listItems[] = self::renderFile([
'target'         => $EL['target'],
'targetOrg'      => $EL['targetOrg'],
'name'           => $EL['name'],
'ext'            => $EL['ext'],
'lebo'           => $EL['lebo'],
'ribo'           => $EL['ribo'],
'size'           => $EL['size'],
'maxSize'        => $stats['biggestFile'],
'thumbImage'     => $EL['thumbImage'],
'thumbSharp'     => $EL['thumbSharp'],
'thumbInfo'      => $EL['thumbInfo'],
'fileLocation'   => $EL['fileLocation'],
'checkboxValue'  => $EL['checkboxValue'],
'downloadName'   => $EL['downloadName']
]);
$stats['visibleFileCount']++;
$stats['visibleFileSize'] += $EL['size'];
$stats['totalFileCount']++;
$stats['totalFileSize'] += $EL['size'];
} else {
$stats['hiddenFileCount']++;
$stats['hiddenFileSize'] += $EL['size'];
$stats['totalFileCount']++;
$stats['totalFileSize'] += $EL['size'];
}
}
}
}
//-- 6. VERSTECKTE DATEIEN INFO
if ($showHideExtensionsInfo && $stats['hiddenFileCount'] > 0) {
$listItems[] = self::renderHiddenInfo([
'count'   => $stats['hiddenFileCount'],
'size'    => $stats['hiddenFileSize'],
'maxSize' => $stats['biggestFile']
]);
}
self::debug("Ergebnis: " . count($listItems) . " Listenelemente");
self::debug("[FV_UI] ========== buildListItems ENDE ==========");
return [
'listItems' => $listItems,
'stats' => $stats
];
}
########################### ORDNER-ELEMENTE
##### RENDERT EINEN ORDNER-EINTRAG
public static function renderFolder(array $data): string
{
$target        = htmlentities($data['target']);
$name          = htmlentities(FV_Helper::formatName($data['name']));
$lebo          = $data['lebo'];
$ribo          = $data['ribo'];
$size          = $data['size'];
$maxSize       = $data['maxSize'] ?? 0.0001;
$checkboxValue = isset($data['checkboxValue']) ? htmlentities($data['checkboxValue']) : '';
$percentWidth = round($size / max($maxSize, 0.0001) * 100);
$html = '
<li class="dir" title="' . htmlentities($data['name']) . '">
<a href="' . $target . '" class="main-link" tabindex="1" id="' . crc32($data['name']) . '">
<span class="thumb" data-ic="folder-open">' . FV_Lang::get('folder') . '</span>
<i>' . $name . '</i>
<span class="lebo">' . $lebo . '</span>
<span class="ribo">' . $ribo . '</span>
<span class="percent"><span style="width: ' . $percentWidth . '%;"></span></span>
</a>';
if ($checkboxValue) {
$html .= '<label><input type="checkbox" value="' . $checkboxValue . '" name="data[]" /></label>';
}
$html .= '
</li>';
return $html;
}
##### RENDERT DEN CACHE-ORDNER (SYSTEMORDNER)
public static function renderCacheFolder(array $data): string
{
self::debug("renderCacheFolder aufgerufen");
$target  = htmlentities($data['target']);
$ribo    = $data['ribo'];
$size    = $data['size'];
$maxSize = $data['maxSize'] ?? 0.0001;
$logoUrl = htmlentities(FV_Core::insPath(true) . '?file=logo');
return '
<li class="dir">
<a href="' . $target . '" class="main-link">
<span class="thumb" data-ic="folder-o">' . FV_Lang::get('folder') . '</span>
<i><img src="' . $logoUrl . '" alt="" style="height: var(--font-size-base);vertical-align: middle;" /> Cache</i>
<span class="lebo">' . FV_Lang::get('systemFolder') . '</span>
<span class="ribo"></span>
</a>
</li>';
}
########################### DATEI-ELEMENTE
##### RENDERT EINEN DATEI-EINTRAG MIT THUMBNAIL UND AKTIONSLINKS
public static function renderFile(array $data): string
{
$target        = htmlentities($data['target']);
$targetOrg     = htmlentities($data['targetOrg']);
$name          = htmlentities(FV_Helper::formatName($data['name']));
$ext           = $data['ext'] ?? '';
$lebo          = $data['lebo'];
$ribo          = $data['ribo'];
$size          = $data['size'];
$maxSize       = $data['maxSize'] ?? 0.0001;
$checkboxValue = isset($data['checkboxValue']) ? htmlentities($data['checkboxValue']) : '';
$downloadName  = isset($data['downloadName']) ? htmlentities($data['downloadName']) : $name . ($ext ? '.' . $ext : '');
$percentWidth = round($size / max($maxSize, 0.0001) * 100);
$thumbHtml = self::renderFileThumb($data);
$bgStyle = !empty($data['thumbImage'])
? ' style="background-image: url(\'' . htmlentities($data['thumbImage']) . '\');"'
: '';
$thumbInfoHtml = !empty($data['thumbInfo'])
? '<span class="thimb">' . $data['thumbInfo'] . '</span>'
: '';
$idAttr = isset($data['fileLocation'])
? ' id="' . crc32($data['fileLocation']) . '"'
: '';
$html = '
<li class="itm"' . $bgStyle . ' title="' . htmlentities($data['name']) . '">
<a class="main-link" href="' . $target . '" target="_blank" tabindex="1"' . $idAttr . '>
' . $thumbHtml . $thumbInfoHtml . '
<i>' . $name . ($ext ? '<span class="micro">.' . $ext . '</span>' : '') . '</i>
<span class="lebo">' . $lebo . '</span>
<span class="ribo">' . $ribo . '</span>
<span class="percent"><span style="width: ' . $percentWidth . '%;"></span></span>
</a>
<a class="action-link" href="' . $targetOrg . '" aria-label="' . FV_Lang::get('download') . '" download="' . $downloadName . '" data-ic="download"></a>
<a class="action-link" href="#' . crc32($data['fileLocation'] ?? $data['name']) . '" data-ic="share-square-o"></a>';
if ($checkboxValue) {
$html .= '<label><input type="checkbox" value="' . $checkboxValue . '" name="data[]" /></label>';
}
$html .= '
</li>';
return $html;
}
##### RENDERT DAS THUMBNAIL EINER DATEI (BILD ODER ICON)
private static function renderFileThumb(array $data): string
{
if (!empty($data['thumbImage'])) {
$style = 'background-image: url(' . htmlentities($data['thumbImage']) . ');';
if (!empty($data['thumbSharp'])) {
$style .= 'image-rendering: pixelate;image-rendering: crisp-edges;';
}
return '<span class="thumb" style="' . $style . '"></span>';
}
$ext = $data['ext'] ?? '';
$imgInfos = FV_Helper::mimeInfoFilter(['extension' => $ext]);
if ($imgInfos) {
$icName = $imgInfos[0]['icName'];
return '<span class="thumb" data-ic="' . $icName . '">' . ($ext ?: '-') . '</span>';
}
return '<span class="thumb" data-ic="file">' . ($ext ?: '-') . '</span>';
}
########################### INFO-ELEMENTE
##### RENDERT DIE INFO-LEISTE FÜR VERSTECKTE DATEIEN
public static function renderHiddenInfo(array $data): string
{
$count   = $data['count'];
$size    = $data['size'];
$maxSize = $data['maxSize'] ?? 0.0001;
$avgSize = $count > 0 ? $size / $count : 0;
$percentWidth = round($avgSize / max($maxSize, 0.0001) * 100);
return '
<li style="pointer-events: none;">
<a href="#" class="main-link">
<span class="thumb" data-ic="eye-slash">' . $count . '</span>
<i>' . FV_Lang::get('hiddenFiles') . '</i>
<span class="lebo">' . $count . ' ' . FV_Lang::get('files') . '</span>
<span class="ribo">' . FV_Helper::formatByte($size) . '</span>
<span class="percent"><span style="width: ' . $percentWidth . '%;"></span></span>
</a>
</li>';
}
}
?>
<?php
/**
* FV_Helper – Statische Hilfsfunktionen für den E2 FileViewer
* =============================================================
*
* Diese Klasse bündelt alle allgemeinen Hilfsfunktionen des FileViewers.
* Sie enthält Formatierungsfunktionen für Datum, Byte-Größen und Namen,
* MIME/Icon-Zuordnungen, Pfad-Normalisierung, HTTP-Cache-Header und
* CSRF-Schutz.
*
*
* KERN-FUNKTIONEN
* ---------------
* - MIME-Typen und Icons für Datei-Erweiterungen
* - CSRF-Token-Generierung und -Validierung
* - Datumsformatierung mit "heute/gestern/morgen" Erkennung
* - Byte-Größen-Formatierung (B, KB, MB, GB, TB)
* - Pfad-Normalisierung (Windows/Unix, Slash-Konvertierung)
* - HTTP-Cache-Header für optimierte Auslieferung
*
*
* MIME/ICON-TABELLE
* -----------------
* Die Klasse enthält eine umfangreiche Liste von Dateitypen mit:
*   - contentType: HTTP-Content-Type
*   - extension: Dateiendung
*   - icName: Font-Icon-Name (aus FV_UI::icArr)
*   - type: Kategorie (image, office, audio, video, etc.)
*
*
* CSRF-SCHUTZ
* -----------
* - getCsrfToken(): Holt oder generiert ein Token (32 Byte, bin2hex)
* - validateCsrfToken(): Validiert POST-Token, erneuert nach Erfolg
* - csrfField(): Gibt HTML-Hidden-Field zurück
*
*
* VERWENDUNG
* ----------
*   // Formatierung
*   echo FV_Helper::formatByte(1234567);        // 1,23 MB
*   echo FV_Helper::formatDate(time());         // heute, 14:30
*   echo FV_Helper::formatName('langer_dateiname.pdf'); // gekürzt
*
*   // CSRF im Formular
*   echo '<form method="post">' . FV_Helper::csrfField() . '</form>';
*
*   // MIME-Info abfragen
*   $info = FV_Helper::mimeInfoFilter(['extension' => 'pdf']);
*
*
* PFAD-NORMALISIERUNG
* -------------------
*   // Windows-Pfad in Unix-Format umwandeln
*   $path = FV_Helper::slashBeautifier('C:\\xampp\\htdocs\\');
*   // Ergebnis: C:/xampp/htdocs/
*
*   // URL-Pfad mit Schema und Host
*   $url = FV_Helper::slashBeautifier('https://example.com//test//');
*   // Ergebnis: https://example.com/test/
*
*
* DATUMSFORMATIERUNG
* ------------------
*   // Level 1: nur Datum (heute, gestern, morgen)
*   // Level 2: Datum + Uhrzeit (heute, 14:30)
*   // Level 3: Datum + Uhrzeit mit Sekunden (heute, 14:30:00)
*   echo FV_Helper::formatDate(strtotime('2025-01-01'), 2);
*
*
* BYTE-FORMATIERUNG
* -----------------
*   // Automatische Einheiten (B, KB, MB, GB, TB)
*   echo FV_Helper::formatByte(1536000); // 1,53 MB
*
*
* CACHE-HEADER
* ------------
*   // Setzt HTTP-Header für öffentliches Caching (3600 Sekunden)
*   FV_Helper::headerCache(3600);
*
* =====================================================================
*/
class FV_Helper
{
########################### MIME / ICONS
##### INTERNE MIME-TABELLE MIT TYPEN, ICONS UND CONTENT-TYPES
private static array $mimeList = [
['contentType' => 'image/jpeg',                  'extension' => 'jpg',   'icName' => 'picture-o', 'type' => 'image'],
['contentType' => 'image/jpeg',                  'extension' => 'jpeg',  'icName' => 'picture-o', 'type' => 'image'],
['contentType' => 'image/x-png',                 'extension' => 'png',   'icName' => 'picture-o', 'type' => 'image'],
['contentType' => 'image/gif',                   'extension' => 'gif',   'icName' => 'picture-o', 'type' => 'image'],
['contentType' => 'image/webp',                  'extension' => 'webp',  'icName' => 'picture-o', 'type' => 'image'],
['contentType' => 'application/pdf',             'extension' => 'pdf',   'icName' => 'book',      'type' => 'office'],
['contentType' => 'application/msword',          'extension' => 'doc',   'icName' => 'book',      'type' => 'office'],
['contentType' => 'application/msword',          'extension' => 'docx',  'icName' => 'book',      'type' => 'office'],
['contentType' => 'application/x-msexcel',       'extension' => 'xls',   'icName' => 'calendar-2', 'type' => 'office'],
['contentType' => 'application/x-msexcel',       'extension' => 'xlsx',  'icName' => 'calendar-2', 'type' => 'office'],
['contentType' => 'application/',                'extension' => 'exe',   'icName' => 'gear',      'type' => 'app'],
['contentType' => 'application/',                'extension' => 'dll',   'icName' => 'gear',      'type' => 'app'],
['contentType' => 'application/',                'extension' => '7z',    'icName' => 'archive',   'type' => 'archive'],
['contentType' => 'application/',                'extension' => 'rar',   'icName' => 'archive',   'type' => 'archive'],
['contentType' => 'application/zip',             'extension' => 'zip',   'icName' => 'archive',   'type' => 'archive'],
['contentType' => 'text/plain',                  'extension' => 'txt',   'icName' => 'file-text-o', 'type' => 'text'],
['contentType' => 'audio/',                      'extension' => 'wav',   'icName' => 'volume-up', 'type' => 'audio'],
['contentType' => 'audio/mpeg',                  'extension' => 'mp3',   'icName' => 'volume-up', 'type' => 'audio'],
['contentType' => 'audio/ogg',                   'extension' => 'ogg',   'icName' => 'volume-up', 'type' => 'audio'],
['contentType' => 'text/',                       'extension' => 'htaccess', 'icName' => 'code',    'type' => 'web'],
['contentType' => 'text/',                       'extension' => 'php',   'icName' => 'code',      'type' => 'web'],
['contentType' => 'text/html',                   'extension' => 'html',  'icName' => 'code',      'type' => 'web'],
['contentType' => 'text/html',                   'extension' => 'htm',   'icName' => 'code',      'type' => 'web'],
['contentType' => 'application/javascript',      'extension' => 'js',    'icName' => 'code',      'type' => 'web'],
['contentType' => 'text/css',                    'extension' => 'css',   'icName' => 'leaf',      'type' => 'web'],
['contentType' => 'application/font',            'extension' => 'eot',   'icName' => 'font',      'type' => 'font'],
['contentType' => 'application/x-font-TrueType', 'extension' => 'ttf',   'icName' => 'font',      'type' => 'font'],
['contentType' => 'application/octet-stream',    'extension' => 'otf',   'icName' => 'font',      'type' => 'font'],
['contentType' => 'application/x-font-woff',     'extension' => 'woff',  'icName' => 'font',      'type' => 'font'],
['contentType' => 'application/x-font-woff2',    'extension' => 'woff2', 'icName' => 'font',      'type' => 'font'],
['contentType' => 'image/svg+xml',               'extension' => 'svg',   'icName' => 'font',      'type' => 'font'],
['contentType' => 'video/mp4',                   'extension' => 'mp4',   'icName' => 'play',      'type' => 'video'],
['contentType' => 'video/',                      'extension' => 'mpg',   'icName' => 'play',      'type' => 'video'],
['contentType' => 'video/',                      'extension' => 'wmv',   'icName' => 'play',      'type' => 'video'],
['contentType' => 'video/',                      'extension' => 'avi',   'icName' => 'play',      'type' => 'video'],
['contentType' => 'video/',                      'extension' => 'xvid',  'icName' => 'play',      'type' => 'video'],
['contentType' => 'video/',                      'extension' => 'divx',  'icName' => 'play',      'type' => 'video'],
];
##### SUCHT MIME-INFO NACH FILTERKRITERIEN (EXTENSION, TYPE, CONTENTTYPE)
public static function mimeInfoFilter(array $filter = []): array
{
$ret = [];
//-- Durch MIME-Liste iterieren und passende Einträge sammeln
foreach (self::$mimeList as $item) {
$add = true;
if (isset($filter['contentType']) && mb_strtolower($item['contentType']) != mb_strtolower($filter['contentType'])) $add = false;
if (isset($filter['extension']) && mb_strtolower($item['extension']) != mb_strtolower($filter['extension'])) $add = false;
if (isset($filter['icName']) && mb_strtolower($item['icName']) != mb_strtolower($filter['icName'])) $add = false;
if (isset($filter['type']) && mb_strtolower($item['type']) != mb_strtolower($filter['type'])) $add = false;
if ($add) $ret[] = $item;
}
return $ret;
}
########################### CSRF SCHUTZ
##### GENERIERT ODER GIBT DEN AKTUELLEN CSRF-TOKEN ZURÜCK
public static function getCsrfToken(): string
{
//-- Token in der Session erzeugen, falls nicht vorhanden
if (!isset($_SESSION['csrfToken'])) {
$_SESSION['csrfToken'] = bin2hex(random_bytes(32));
}
return $_SESSION['csrfToken'];
}
##### VALIDIERT DEN CSRF-TOKEN AUS DEM POST-REQUEST
public static function validateCsrfToken(): bool
{
//-- Nur bei POST-Requests prüfen
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
return true;
}
$token = $_POST['csrfToken'] ?? '';
if (empty($token) || $token !== self::getCsrfToken()) {
return false;
}
//-- Token nach erfolgreicher Validierung erneuern
$_SESSION['csrfToken'] = bin2hex(random_bytes(32));
return true;
}
##### GIBT DAS HTML FÜR DAS CSRF-HIDDEN-FIELD ZURÜCK
public static function csrfField(): string
{
return '<input type="hidden" name="csrfToken" value="' . htmlspecialchars(self::getCsrfToken()) . '" />';
}
########################### FORMATIERUNG
##### FORMATIERT EIN DATUM MIT "HEUTE/GESTERN/MORGEN" ERKENNUNG
public static function formatDate($UTS = false, int $level = 2): string
{
//-- Standard: aktuelle Zeit
if ($UTS === false) $UTS = time();
$intUTS = (int) $UTS;
//-- Datumsstring in Timestamp umwandeln
if (is_string($UTS) && str_contains($UTS, ':') && str_contains($UTS, '-')) {
$UTS = strtotime($UTS);
} elseif ($intUTS == $UTS) {
$UTS = $intUTS;
} else {
$UTS = false;
}
if (!$UTS || $UTS <= 0) return 'k.A.';
$day        = date('j', $UTS);
$month      = date('n', $UTS);
$year       = date('Y', $UTS);
$todayStart = strtotime(date('Y-m-d') . ' 00:00:00');
$months = [
1 => 'Jan',
2 => 'Feb',
3 => 'Mär',
4 => 'Apr',
5 => 'Mai',
6 => 'Jun',
7 => 'Jul',
8 => 'Aug',
9 => 'Sep',
10 => 'Okt',
11 => 'Nov',
12 => 'Dez'
];
$time = ($level == 1) ? '' : (($level == 3) ? date(', H:i:s', $UTS) : date(', H:i', $UTS));
//-- Relative Datumsangaben (heute, gestern, morgen)
if ($UTS >= $todayStart - 2 * 86400 && $UTS < $todayStart + 3 * 86400) {
if ($UTS < $todayStart - 86400) return 'vorgestern' . $time;
if ($UTS < $todayStart) return 'gestern' . $time;
if ($UTS < $todayStart + 86400) return 'heute' . $time;
if ($UTS < $todayStart + 2 * 86400) return 'morgen' . $time;
return 'übermorgen' . $time;
}
$yearStr = ($year == date('Y')) ? '' : ' ' . $year;
return $day . '. ' . $months[$month] . $yearStr . $time;
}
##### FORMATIERT EINE BYTE-GRÖSSE (B, KB, MB, GB, TB)
public static function formatByte($size): string
{
//-- Als float casten für Berechnungen
$size = (float) $size;
//-- Einheit und Teiler bestimmen
if ($size > 1e12) {
$div = 1e12;
$unit = 'TB';
$st = 2;
} elseif ($size > 1e9) {
$div = 1e9;
$unit = 'GB';
$st = 2;
} elseif ($size > 1e6) {
$div = 1e6;
$unit = 'MB';
$st = 2;
} elseif ($size > 1e3) {
$div = 1e3;
$unit = 'KB';
$st = 2;
} else {
$div = 1;
$unit = 'B';
$st = 0;
}
$val = number_format($size / $div, $st, ',', '');
//-- Entferne unnötige Nachkommastellen
if ($st == 2) $val = str_replace(',00', '', $val);
elseif ($st == 1) $val = str_replace(',0', '', $val);
return $val . ' ' . $unit;
}
##### KÜRZT EINEN NAMEN AUF MAXIMAL 40 ZEICHEN
public static function formatName(string $name): string
{
if (strlen($name) > 40) {
$a = substr($name, 0, 20);
$b = substr($name, -12);
return $a . '...' . $b;
}
return $name;
}
##### NORMALISIERT EINEN PFAD (SLASH-KONVERTIERUNG, DOPPELTE SLASHES ENTFERNEN)
public static function slashBeautifier($p, bool $exploded = false)
{
if ($p === false) return false;
$original = $p;
//-- Windows-Laufwerksbuchstaben erhalten (z.B. C:/)
$p = preg_replace_callback('#^([a-zA-Z]):/#', function ($m) {
return $m[1] . ':/';
}, $p);
$p = str_replace(':\\\\', '://', $p);
//-- URL-Parsing für URLs mit Schema
if ($k = parse_url($p)) {
$scheme = $k['scheme'] ?? '';
$host = $k['host'] ?? '';
$path = $k['path'] ?? '';
if ($host) {
$host = preg_replace('#[/\\\\]+#', '/', $host);
}
if ($path) {
$path = preg_replace('#[/\\\\]+#', '/', $path);
}
if ($scheme && !preg_match('/^[a-z]$/i', $scheme)) {
$scheme = rtrim($scheme, ':') . ':';
}
//-- Trailing Slash beibehalten wenn im Original vorhanden
if (str_ends_with($original, '/') && !str_ends_with($path, '/') && !preg_match('/\.[^\/]+$/', $path)) {
$path .= '/';
}
if ($exploded) {
return [
'scheme' => $scheme ? $scheme . ':' : '',
'host' => $host ? '//' . $host : '',
'path' => $path
];
}
return ($scheme ? $scheme . ':' : '') . ($host ? '//' . $host : '') . $path;
}
//-- Normale Pfad-Normalisierung
$p = str_replace('\\', '/', $p);
$p = preg_replace('#/+#', '/', $p);
if ($exploded) {
return [
'scheme' => '',
'host' => '',
'path' => $p
];
}
return $p;
}
########################### ARRAY-HILFEN
##### EXTRAHIERT EINEN BESTIMMTEN KEY AUS EINEM ARRAY VON ARRAYS
public static function softImplode(array $arr = [], string $key = ''): array
{
$ret = [];
foreach ($arr as $item) {
if (isset($item[$key])) {
$ret[] = $item[$key];
}
}
return $ret;
}
########################### CACHE-HILFEN (HTTP)
##### SETZT HTTP-CACHE-HEADER FÜR OPTIMIERTE AUSLIEFERUNG
public static function headerCache(int $seconds): void
{
$seconds = (int) $seconds;
header('pragma: ');
header('keep-alive: ');
header('cache-control: public, max-age=' . $seconds);
header('date: ' . date('D, d M Y H:i:s \G\M\T', time()));
header('expires: ' . date('D, d M Y H:i:s \G\M\T', time() + $seconds));
header('last-modified: ' . date('D, d M Y H:i:s \G\M\T', time() - $seconds - 5));
}
}
?>
<?php
/**
* FV_StructureCache – Intelligentes Dateisystem-Caching für Ordnerstrukturen
* =====================================================================
*
* Diese Klasse verwaltet das Caching von Ordnerinhalten, um wiederholte
* Scans des Dateisystems zu vermeiden. Sie speichert Ordnerdaten in
* JSON-Dateien und prüft vor der Verwendung, ob sich die Ordnerstruktur
* seit dem letzten Cache geändert hat.
*
*
* FUNKTIONSWEISE
* --------------
* - Caching von Ordnerinhalten (Dateien und Unterordner)
* - Automatische Erkennung von Änderungen via Snapshot-Hashes
* - Detail-Level 0,1,2 für unterschiedliche Informations-Tiefen
* - Löschung veralteter Cache-Dateien bei Änderungen
* - Optionale Debug-Ausgaben
*
*
* DETAIL-LEVEL
* ------------
* - 0: Nur Basis-Informationen (Name, Größe, Zeit)
* - 1: Zusätzliche Bild-Informationen (Auflösung, EXIF)
* - 2: Alle verfügbaren Informationen
*
*
* CACHE-DATEI-NAMENSSCHEMA
* ------------------------
* c_{lesbarerName}_{absoluterPfadHash}_{detail}.json
*
* Beispiel: c_root_abc123def456_1.json
*
*
* CACHE-VALIDIERUNG
* -----------------
* 1. Snapshot-Hash des aktuellen Ordners berechnen (nur Namen, Größen, Zeiten)
* 2. Mit gespeichertem Hash aus Cache-Datei vergleichen
* 3. Bei Abweichung: Cache löschen und neu aufbauen
*
*
* SCHWELLWERT
* -----------
* Ordner mit weniger als MIN_ENTRIES_FOR_CACHE Einträgen werden nicht gecacht, um
* die Cache-Performance nicht durch viele kleine Dateien zu belasten.
*
*
* ABHÄNGIGKEITEN
* --------------
* - FV_FileSystem: Für Dateisystem-Operationen (Löschen, Verzeichnisse erstellen)
*   Wird über den Konstruktor injiziert.
*
*
* VERWENDUNG
* ----------
*   $cache = new FV_StructureCache('/pfad/zum/cache/', '/pfad/zum/scan/', true, null, new FV_FileSystem());
*   $data = $cache->get('/pfad/zum/ordner/', 1);
*   if ($data === false) {
*       $data = scanDirectory($dir);
*       $cache->set('/pfad/zum/ordner/', 1, $data);
*   }
*
* =====================================================================
*/
class FV_StructureCache
{
########################### EIGENSCHAFTEN
##### CACHE-VERZEICHNIS (PHYSIKALISCHER PFAD)
private string $cacheDir;
private string $scanDir;
private bool $enabled;
private bool $debug = false;
private $readDirInfoCallback = null;
private FV_FileSystem $CL_FileSystem;
##### SCHWELLWERT: ORDNER MIT WENIGER EINTRÄGEN WERDEN NICHT GECACHT
private const MIN_ENTRIES_FOR_CACHE = 3;
########################### KONSTRUKTOR & BASIS-METHODEN
##### KONSTRUKTOR: SETZT PFADE, DEBUG, INJIZIERT FILESYSTEM
public function __construct(
string $cacheDir,
string $scanDir,
bool $enabled = true,
callable $readDirInfoCallback = null,
?FV_FileSystem $fileSystem = null
) {
$this->cacheDir            = rtrim($cacheDir, '/') . '/';
$this->scanDir             = rtrim($scanDir, '/') . '/';
$this->enabled             = $enabled;
$this->readDirInfoCallback = $readDirInfoCallback;
$this->debug               = FV_Core::isDebugEnabled(strtolower(__CLASS__));
$this->CL_FileSystem       = $fileSystem ?? new FV_FileSystem();
}
##### INTERNER DEBUG-AUSGABE (NUR BEI AKTIVIERTEM DEBUG)
private function debug(string $message): void
{
if ($this->debug) {
error_log("[SC] " . $message);
}
}
##### SETZT DEBUG-MODUS
public function setDebug(bool $debug): void
{
$this->debug = $debug;
}
##### GIBT ZURÜCK, OB CACHE AKTIVIERT IST
public function isEnabled(): bool
{
return $this->enabled;
}
##### NORMALISIERT PFAD (SCHLUSS-SLASH)
private function normalizePath(string $path): string
{
return rtrim($path, '/') . '/';
}
##### PRÜFT, OB EIN PFAD IMNERHALB DES CACHE-VERZEICHNIS LIEGT
private function isInsideCacheDir(string $path): bool
{
$pathReal = $this->normalizePath($path);
return strpos($pathReal, $this->cacheDir) === 0;
}
########################### CACHE-DATEI-PFAD & NAMENSGEBUNG
##### ERZEUGT EINEN LESBAREN BASENAMEN AUS RELATIVEM PFAD
private function getReadableBaseName(string $relativePath, int $maxLength = 80): string
{
if (empty($relativePath)) {
return 'root';
}
//-- Ersetze Pfad-Trenner durch Unterstriche
$clean = str_replace(['/', '\\', ' '], '_', $relativePath);
//-- Entferne ungültige Zeichen
$clean = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $clean);
//-- Reduziere mehrere Unterstriche auf einen
$clean = preg_replace('/_+/', '_', $clean);
$clean = trim($clean, '_');
if (empty($clean)) {
return 'root_' . substr(md5($relativePath), 0, 8);
}
//-- Bei zu langem Namen: kürzen + Hash anhängen
if (strlen($clean) > $maxLength) {
$hash = substr(md5($relativePath), 0, 8);
$clean = substr($clean, 0, $maxLength - 9) . '_' . $hash;
$clean = rtrim($clean, '_');
}
return $clean;
}
##### ERMITTELT DEN VOLLSTÄNDIGEN CACHE-DATEIPFAD
private function getCacheFilePath(string $realDir, int $detail): string
{
$realDir = $this->normalizePath($realDir);
$absHash = md5($realDir);
//-- Relativen Pfad für lesbaren Namen extrahieren
$relative = '';
if (str_starts_with($realDir, $this->scanDir)) {
$relative = substr($realDir, strlen($this->scanDir));
$relative = trim($relative, '/');
}
$readable = $this->getReadableBaseName($relative);
return $this->cacheDir . 'c_' . $readable . '_' . $absHash . '_' . $detail . '.json';
}
########################### CACHE-BEREINIGUNG
##### LÖSCHT NIEDRIGERE DETAIL-LEVEL, WENN HÖHERER GESPEICHERT WURDE
private function cleanLowerDetailLevels(string $realDir, int $savedDetail): void
{
if (!$this->enabled) return;
$cacheJsonDir = $this->cacheDir;
if (!is_dir($cacheJsonDir)) return;
//-- Relativen Pfad für BaseName ermitteln
$relative = '';
if (str_starts_with($realDir, $this->scanDir)) {
$relative = substr($realDir, strlen($this->scanDir));
$relative = trim($relative, '/');
}
$baseName = $this->getReadableBaseName($relative);
$absHash = md5($realDir);
//-- Je nach gespeichertem Level die niedrigeren löschen
if ($savedDetail >= 2) {
$this->unlinkIfExists($cacheJsonDir . 'c_' . $baseName . '_' . $absHash . '_1.json');
$this->unlinkIfExists($cacheJsonDir . 'c_' . $baseName . '_' . $absHash . '_0.json');
} elseif ($savedDetail >= 1) {
$this->unlinkIfExists($cacheJsonDir . 'c_' . $baseName . '_' . $absHash . '_0.json');
}
}
##### LÖSCHT EINE DATEI, FALLS SIE EXISTIERT
private function unlinkIfExists(string $file): void
{
if (file_exists($file)) {
$this->debug("Lösche: " . basename($file));
$this->CL_FileSystem->deleteFileInstance($file, false);
}
}
########################### SNAPSHOT-ERSTELLUNG
##### ERSTELLT EINEN SCHNELLEN SNAPSHOT (NAME, GRÖSSE, ZEIT)
private function getQuickSnapshot(string $dir): array
{
$items = [];
$dirReal = $this->normalizePath($dir);
$this->debug("getQuickSnapshot für: " . $dirReal);
//-- Cache-Ordner selbst ignorieren
if ($this->isInsideCacheDir($dirReal)) {
$this->debug("-> Ordner im Cache-Baum, leerer Hash");
return ['hash' => hash('sha256', '')];
}
try {
$iterator = new FilesystemIterator($dirReal);
$dirCount = 0;
$fileCount = 0;
foreach ($iterator as $item) {
$name = $item->getFilename();
$fullPath = $item->getPathname();
$isDir = $item->isDir();
$pathForCheck = $fullPath . ($isDir ? '/' : '');
//-- Cache-Ordner-Inhalte überspringen
if ($this->isInsideCacheDir($pathForCheck)) {
$this->debug("  Ignoriere Cache-Item: " . $name);
continue;
}
if ($isDir) {
//-- Für Ordner: Name, Datei-Anzahl, Gesamtgröße
$subInfo = $this->readDirInfo($fullPath . '/', 0);
$items[] = 'D:' . $name . '|' . ($subInfo['totalCount'] ?? 0) . '|' . ($subInfo['totalSize'] ?? 0);
$dirCount++;
} else {
//-- Für Dateien: Name, Größe, Änderungszeit
$items[] = 'F:' . $name . '|' . $item->getSize() . '|' . $item->getMTime();
$fileCount++;
}
}
sort($items);
$hash = hash('sha256', implode('', $items));
$this->debug("Statistik: Dirs=$dirCount, Files=$fileCount");
$this->debug("Hash: " . $hash);
return ['hash' => $hash];
} catch (UnexpectedValueException $e) {
$this->debug("FEHLER beim Lesen von $dirReal: " . $e->getMessage());
return ['hash' => hash('sha256', '')];
}
}
##### RUFT DEN READDIRINFO-CALLBACK AUF
private function readDirInfo(string $dir, int $detail)
{
if ($this->readDirInfoCallback) {
return call_user_func($this->readDirInfoCallback, $dir, $detail);
}
return false;
}
##### BERECHNET SNAPSHOT-HASH AUS EINEM VOLLSTÄNDIGEN SCAN-ERGEBNIS
private function calcSnapshotFromResult(array $result): string
{
$items = [];
foreach ($result['dirList'] as $dir) {
$items[] = 'D:' . $dir['name'] . '|' . ($dir['dirFileCount'] ?? 0) . '|' . ($dir['dirFileSize'] ?? 0);
}
foreach ($result['fileList'] as $file) {
$items[] = 'F:' . $file['fileName'] . '|' . $file['fileSize'] . '|' . $file['fileTime'];
}
sort($items);
return hash('sha256', implode('', $items));
}
########################### CACHE-ZUGRIFF (GET / SET)
##### HOLT GECACHTE ORDNERDATEN (FALLS VALIDE)
public function get(string $realDir, int $detail)
{
if (!$this->enabled) return false;
$realDir = $this->normalizePath($realDir);
//-- Cache-Ordner selbst wird nicht gecacht
if ($this->isInsideCacheDir($realDir)) return false;
//-- Durchsuche alle möglichen Cache-Level (0,1,2)
$candidates = [];
for ($level = 0; $level <= 2; $level++) {
$file = $this->getCacheFilePath($realDir, $level);
if (file_exists($file)) {
$candidates[$level] = $file;
}
}
if (empty($candidates)) return false;
//-- Prüfe zuerst die höheren Level (2,1,0) – weil sie evtl. mehr Daten enthalten
krsort($candidates);
foreach ($candidates as $level => $file) {
$content = file_get_contents($file);
if ($content === false) continue;
$cache = json_decode($content, true);
if ($cache === null || !isset($cache['snapshotHash'])) {
$this->CL_FileSystem->deleteFileInstance($file, false);
continue;
}
//-- Prüfe Hash (Änderungserkennung)
$currentSnapshot = $this->getQuickSnapshot($realDir);
if ($cache['snapshotHash'] !== $currentSnapshot['hash']) {
$this->CL_FileSystem->deleteFileInstance($file, false);
continue;
}
//-- Prüfe, ob der Ordner zu klein ist (weniger als MIN_ENTRIES_FOR_CACHE Einträge)
$entryCount = $this->getEntryCountFromCacheData($cache['data']);
if ($entryCount < self::MIN_ENTRIES_FOR_CACHE) {
$this->debug("-> Ordner zu klein ($entryCount < " . self::MIN_ENTRIES_FOR_CACHE . "), lösche Cache und behandle als nicht gecacht");
$this->CL_FileSystem->deleteFileInstance($file, false);
continue;
}
//-- asGoodAsDetail aus dem Cache lesen (falls nicht vorhanden, = detail)
$asGoodAsDetail = $cache['asGoodAsDetail'] ?? $cache['detail'];
//-- Wenn der angeforderte Detail-Level kleiner oder gleich asGoodAsDetail ist,
//-- können die Daten verwendet werden.
if ($detail <= $asGoodAsDetail) {
$this->debug("get: Verwende Cache Level $level (asGoodAsDetail=$asGoodAsDetail) für Detail=$detail");
return $cache['data'];
}
}
return false;
}
##### SPEICHERT ORDNERDATEN IM CACHE
public function set(string $realDir, int $detail, array $data, ?string $snapshotHash = null): bool
{
if (!$this->enabled) return false;
$realDir = $this->normalizePath($realDir);
if ($this->isInsideCacheDir($realDir)) return false;
//-- Prüfe, ob der Ordner groß genug ist, um gecacht zu werden
$entryCount = $this->getEntryCountFromCacheData($data);
if ($entryCount < self::MIN_ENTRIES_FOR_CACHE) {
$this->debug("set: Ordner zu klein ($entryCount < " . self::MIN_ENTRIES_FOR_CACHE . "), kein Cache angelegt");
return false;
}
$cacheJsonDir = dirname($this->getCacheFilePath($realDir, $detail));
if (!is_dir($cacheJsonDir)) {
$this->debug("set: Erstelle JSON-Verzeichnis: " . $cacheJsonDir);
if (!$this->CL_FileSystem->createDirInstance($cacheJsonDir)) return false;
}
$cacheFile = $this->getCacheFilePath($realDir, $detail);
if ($snapshotHash === null) $snapshotHash = $this->getQuickSnapshot($realDir)['hash'];
$this->debug("set: Speichere " . basename($cacheFile) . " mit Hash " . $snapshotHash);
//-- asGoodAsDetail aus den Daten holen (von readDirInfo gesetzt)
$asGoodAsDetail = $data['asGoodAsDetail'] ?? $detail;
$cache = [
'snapshotHash'   => $snapshotHash,
'snapshotTime'   => time(),
'realDir'        => $realDir,
'detail'         => $detail,
'asGoodAsDetail' => $asGoodAsDetail,
'data'           => $data
];
//-- Mit temporärer Datei arbeiten, um Teil-Schreibungen zu vermeiden
$tempFile = $cacheFile . '.tmp';
if (file_put_contents($tempFile, json_encode($cache, JSON_PRETTY_PRINT)) !== false) {
if (rename($tempFile, $cacheFile)) {
$this->debug("-> Cache gespeichert!");
//-- Lösche niedrigere Detail-Level
$this->cleanLowerDetailLevels($realDir, $detail);
return true;
}
}
$this->CL_FileSystem->deleteFileInstance($tempFile, false);
$this->debug("-> Fehler beim Speichern!");
return false;
}
########################### CACHE-LÖSCHUNG
##### LÖSCHT ALLE CACHE-DATEIEN, DIE ZU EINEM ORDNER ODER SEINEN ELTERN GEHÖREN
public function clear(string $path): void
{
if (!$this->enabled) return;
$path = $this->normalizePath($path);
//-- Cache-Ordner selbst nicht löschen
if ($this->isInsideCacheDir($path)) {
$this->debug("clear: Überspringe (im Cache-Baum): " . $path);
return;
}
$cacheJsonDir = $this->cacheDir;
if (!is_dir($cacheJsonDir)) return;
//-- Relativen Pfad für lesbaren Namen ermitteln
$relativePath = '';
if (str_starts_with($path, $this->scanDir)) {
$relativePath = substr($path, strlen($this->scanDir));
$relativePath = trim($relativePath, '/');
}
//-- 1. JSONs für diesen Ordner löschen (alle Detail-Level)
$baseName = $this->getReadableBaseName($relativePath);
$absHash = md5($path);
$pattern = $cacheJsonDir . 'c_' . $baseName . '_' . $absHash . '_*.json';
foreach (glob($pattern) as $file) {
$this->debug("clear: Lösche " . basename($file));
$this->CL_FileSystem->deleteFileInstance($file, false);
}
//-- 2. JSONs für alle Eltern-Ordner löschen (bis zum Root)
$parts = explode('/', $relativePath);
for ($i = 1; $i < count($parts); $i++) {
$parentRelative = implode('/', array_slice($parts, 0, $i));
if (!empty($parentRelative)) {
$parentBase = $this->getReadableBaseName($parentRelative);
$parentReal = $this->scanDir . $parentRelative . '/';
$parentHash = md5($parentReal);
$parentPattern = $cacheJsonDir . 'c_' . $parentBase . '_' . $parentHash . '_*.json';
foreach (glob($parentPattern) as $file) {
$this->debug("clear: Lösche Parent " . basename($file));
$this->CL_FileSystem->deleteFileInstance($file, false);
}
}
}
//-- 3. Root-Cache löschen - spezifisch für diese scanDir (nicht alle Root-Caches)
$rootHash = md5($this->scanDir);
$rootPattern = $cacheJsonDir . 'c_root_' . $rootHash . '_*.json';
foreach (glob($rootPattern) as $file) {
$this->debug("clear: Lösche Root " . basename($file));
$this->CL_FileSystem->deleteFileInstance($file, false);
}
}
########################### HILFSMETHODEN
##### ZÄHLT ORDNER- + DATEI-EINTRÄGE AUS CACHE-DATEN
private function getEntryCountFromCacheData(array $data): int
{
$dirCount = count($data['dirList'] ?? []);
$fileCount = count($data['fileList'] ?? []);
return $dirCount + $fileCount;
}
}
?>
<?php
/**
* FV_Lang – Mehrsprachigkeits-Klasse für den E2 FileViewer
* ======================================================
*
* Diese Klasse verwaltet alle Übersetzungen des FileViewers.
* Sie unterstützt mehrere Sprachen und bietet Funktionen zum
* Abrufen von Übersetzungen, Logging von verwendeten Keys und
* Generierung von JavaScript-kompatiblen Übersetzungslisten.
*
*
* UNTERSTÜTZTE SPRACHEN
* ---------------------
* - de: Deutsch
* - en: Englisch
* - tr: Türkisch
*
*
* ÜBERSETZUNGSSTRUKTUR
* --------------------
* Jeder Übersetzungs-Key enthält ein Array mit Sprachschlüsseln:
* 'keyName' => [
*   'de' => 'Deutscher Text',
*   'en' => 'English text',
*   'tr' => 'Türkçe metin',
* ]
*
*
* PLACEHOLDER-UNTERSTÜTZUNG
* -------------------------
* Übersetzungen können Platzhalter {1}, {2}, {3} ... enthalten:
* '$FolderWith$Files' => 'Ordner {1} mit {2} Datei(en)'
* FV_Lang::get('$FolderWith$Files', 5, 42);
*
*
* JAVASCRIPT-INTEGRATION
* ----------------------
* Über convertListToJs() können alle Übersetzungen als JavaScript-Objekt
* ausgegeben werden, das dann im Frontend über fvLang.get('key') nutzbar ist.
*
*
* UPLOAD-FEHLERMELDUNGEN
* ----------------------
* Die Methode getUploadErrorMessage() liefert passende Meldungen zu
* PHP-Upload-Fehlercodes (UPLOAD_ERR_*).
*
*
* BENACHRICHTIGUNGEN
* ------------------
* Spezielle Übersetzungen für Upload- und Delete-Benachrichtigungen:
* - uploadSuccessMultiple: "X Dateien wurden erfolgreich hochgeladen"
* - deleteSuccessMultiple: "X Elemente wurden erfolgreich gelöscht"
*
*
* VERWENDUNG
* ----------
*   // Sprache setzen
*   FV_Lang::language('de');
*
*   // Übersetzung abrufen
*   echo FV_Lang::get('homePage');                     // "Startseite"
*   echo FV_Lang::get('$FolderWith$Files', 3, 127);    // "3 Ordner mit 127 Datei(en)"
*
*   // Alle Übersetzungen als JS-Objekt
*   echo FV_Lang::convertListToJs(FV_Lang::getAll());
*
* =====================================================================
*/
class FV_Lang
{
########################### SPRACHEINSTELLUNGEN
protected static string $language = 'en';
protected static array $languageList = ['en', 'de', 'tr'];
protected static array $langList = [
// ==================== NAVIGATION & UI ====================
'homePage' => [
'de' => 'Startseite',
'en' => 'homepage',
'tr' => 'ana sayfa',
],
'prev' => [
'de' => 'zurück',
'en' => 'prev',
'tr' => 'geri',
],
'next' => [
'de' => 'vorwärts',
'en' => 'next',
'tr' => 'ileri',
],
'close' => [
'de' => 'schließen',
'en' => 'close',
'tr' => 'kapat',
],
'delete' => [
'de' => 'löschen',
'en' => 'delete',
'tr' => 'sil',
],
'upload' => [
'de' => 'upload',
'en' => 'upload',
'tr' => 'yükle',
],
'download' => [
'de' => 'download',
'en' => 'download',
'tr' => 'indir',
],
'share' => [
'de' => 'teilen',
'en' => 'share',
'tr' => 'paylaş',
],
// ==================== DATEIEN & ORDNER ====================
'folder' => [
'de' => 'Ordner',
'en' => 'Folder',
'tr' => 'klasör',
],
'file' => [
'de' => 'Datei',
'en' => 'file',
'tr' => 'dosya',
],
'files' => [
'de' => 'Dateien',
'en' => 'files',
'tr' => 'dosyalar',
],
'hidden' => [
'de' => 'versteckt',
'en' => 'hidden',
'tr' => 'gizli',
],
'hiddenFiles' => [
'de' => 'versteckte Datei(en)',
'en' => 'hidden files',
'tr' => 'gizli dosya(lar)',
],
'$FolderWith$Files' => [
'de' => '{1} Ordner mit {2} Datei(en)',
'en' => '{1} folders with {2} file(s)',
'tr' => '{1} klasör, {2} dosya',
],
// ==================== EXPLORER ACTIONS & ALERTS ====================
'deleteSelectElements' => [
'de' => 'Zu löschende Elemente auswählen..',
'en' => 'Select elements to delete..',
'tr' => 'Silinecek öğeleri seçin..',
],
'deleteMarked' => [
'de' => '{1} zu löschende Elemente markiert',
'en' => '{1} elements marked for deletion',
'tr' => 'Silinmek üzere {1} öğe işaretlendi',
],
'itemsSelected' => [
'de' => '{1} Elemente ausgewählt',
'en' => '{1} items selected',
'tr' => '{1} öğe seçildi',
],
'clickOn$ToDownload' => [
'de' => 'Klicke auf {1} zum Downloaden',
'en' => 'Click on {1} to download',
'tr' => 'İndirmek için {1} üzerine tıklayın',
],
'noDownloadableFiles' => [
'de' => 'Keine Dateien zum Download verfügbar',
'en' => 'No files available for download',
'tr' => 'İndirilecek dosya yok',
],
'clickOn$ToShare' => [
'de' => 'Klicke auf {1} zum Teilen',
'en' => 'Click on {1} to share',
'tr' => 'Paylaşmak için {1} üzerine tıklayın',
],
'noShareableFiles' => [
'de' => 'Keine Dateien zum Teilen verfügbar',
'en' => 'No files available to share',
'tr' => 'Paylaşılacak dosya yok',
],
// ==================== INFO & FORMULARE ====================
'width' => [
'de' => 'Breite',
'en' => 'width',
'tr' => 'genişlik',
],
'height' => [
'de' => 'Höhe',
'en' => 'height',
'tr' => 'yükseklik',
],
'widthAndHeight' => [
'de' => 'Breite x Höhe (px)',
'en' => 'width x height (px)',
'tr' => 'genişlik x yükseklik (px)',
],
'megaPixel' => [
'de' => 'Mega-Pixel',
'en' => 'mega-pixels',
'tr' => 'Megapiksel',
],
'fileUpload' => [
'de' => 'Datei uploaden',
'en' => 'upload file',
'tr' => 'dosya yükle',
],
'login' => [
'de' => 'Login',
'en' => 'login',
'tr' => 'giriş',
],
'logout' => [
'de' => 'Ausloggen',
'en' => 'logout',
'tr' => 'çıkış',
],
'loginTo' => [
'de' => 'Logge Dich ein..',
'en' => 'log in..',
'tr' => 'giriş yap..',
],
'sorting' => [
'de' => 'Sortierung',
'en' => 'sorting',
'tr' => 'sıralama',
],
'view' => [
'de' => 'Ansicht',
'en' => 'view',
'tr' => 'görünüm',
],
'systemFolder' => [
'de' => 'Systemordner',
'en' => 'system folder',
'tr' => 'sistem klasörü',
],
'items' => [
'de' => 'Items',
'en' => 'items',
'tr' => 'öğe',
],
'item' => [
'de' => 'Item',
'en' => 'item',
'tr' => 'öğe',
],
'inFolder' => [
'de' => 'in Ordner',
'en' => 'in folder',
'tr' => 'klasörde',
],
'optionalSubfolder' => [
'de' => 'in Ordner (optional)',
'en' => 'in folder (optional)',
'tr' => 'klasörde (isteğe bağlı)',
],
'password' => [
'de' => 'Passwort',
'en' => 'password',
'tr' => 'şifre',
],
// ==================== CLIPBOARD & SHARE ====================
'urlCopied' => [
'de' => 'Url wurde in die Zwischenablage kopiert',
'en' => 'URL has been copied to clipboard',
'tr' => 'URL panoya kopyalandı',
],
'copyNotSupported' => [
'de' => 'Kopieren wird von deinem Browser nicht unterstützt',
'en' => 'Copying is not supported by your browser',
'tr' => 'Kopyalama tarayıcınız tarafından desteklenmiyor',
],
// ==================== PFAD-FEHLER ====================
'invalidPath' => [
'de' => 'ungültiger Pfad',
'en' => 'invalid path',
'tr' => 'geçersiz yol',
],
'invalidUrl' => [
'de' => 'ungültige URL',
'en' => 'invalid URL',
'tr' => 'geçersiz URL',
],
'notAllowed' => [
'de' => 'nicht erlaubt',
'en' => 'not allowed',
'tr' => 'izin verilmiyor',
],
// ==================== ORDNER-OPERATIONEN ====================
'folderCreated' => [
'de' => 'Ordner "{1}" wurde angelegt',
'en' => 'folder "{1}" has been created',
'tr' => '"{1}" klasörü oluşturuldu',
],
'folderCreateFailed' => [
'de' => 'Ordner "{1}" konnte nicht erstellt werden',
'en' => 'folder "{1}" could not be created',
'tr' => '"{1}" klasörü oluşturulamadı',
],
'folderExists' => [
'de' => 'Ordner "{1}" existiert bereits',
'en' => 'folder "{1}" already exists',
'tr' => '"{1}" klasörü zaten var',
],
// ==================== UPLOAD-MELDUNGEN ====================
'fileExists' => [
'de' => 'Datei mit Namen "{1}" bereits vorhanden',
'en' => 'file named "{1}" already exists',
'tr' => '"{1}" adlı dosya zaten var',
],
'fileTypeNotAllowed' => [
'de' => '{1}-Dateien nicht erlaubt',
'en' => '{1} files not allowed',
'tr' => '{1} dosyalarına izin verilmiyor',
],
'uploadSuccess' => [
'de' => 'Die Datei "{1}" erfolgreich hochgeladen',
'en' => 'file "{1}" uploaded successfully',
'tr' => '"{1}" dosyası başarıyla yüklendi',
],
'uploadSuccessMultiple' => [
'de' => '{1} Dateien wurden erfolgreich hochgeladen',
'en' => '{1} files uploaded successfully',
'tr' => '{1} dosya başarıyla yüklendi',
],
'uploadFailed' => [
'de' => 'Die Datei "{1}" konnte nicht hochgeladen werden',
'en' => 'file "{1}" could not be uploaded',
'tr' => '"{1}" dosyası yüklenemedi',
],
'noFileOrUnsupported' => [
'de' => 'Keine, oder vom Server nicht unterstützte, Datei',
'en' => 'No file or file not supported by server',
'tr' => 'Dosya yok veya sunucu tarafından desteklenmiyor',
],
// ==================== DELETE-MELDUNGEN ====================
'cacheElemDeleted' => [
'de' => 'Cache-Element wurde gelöscht',
'en' => 'Cache element has been deleted',
'tr' => 'Önbellek öğesi silindi',
],
'folderDeleted' => [
'de' => 'Der Ordner "{1}" wurde gelöscht',
'en' => 'The folder "{1}" has been deleted',
'tr' => '"{1}" klasörü silindi',
],
'fileDeleted' => [
'de' => 'Die Datei "{1}" wurde gelöscht',
'en' => 'The file "{1}" has been deleted',
'tr' => '"{1}" dosyası silindi',
],
'fileDeleteFailed' => [
'de' => 'Die Datei "{1}" konnte nicht gelöscht werden',
'en' => 'The file "{1}" could not be deleted',
'tr' => '"{1}" dosyası silinemedi',
],
'fileNotFound' => [
'de' => 'Die Datei "{1}" konnte nicht gefunden werden',
'en' => 'The file "{1}" could not be found',
'tr' => '"{1}" dosyası bulunamadı',
],
'deleteSuccessMultiple' => [
'de' => '{1} Elemente wurden erfolgreich gelöscht',
'en' => '{1} items deleted successfully',
'tr' => '{1} öğe başarıyla silindi',
],
'deleteSuccessMultipleFiles' => [
'de' => '{1} Dateien wurden erfolgreich gelöscht',
'en' => '{1} files deleted successfully',
'tr' => '{1} dosya başarıyla silindi',
],
'deleteSuccessMultipleFolders' => [
'de' => '{1} Ordner wurden erfolgreich gelöscht',
'en' => '{1} folders deleted successfully',
'tr' => '{1} klasör başarıyla silindi',
],
'deleteErrors' => [
'de' => '{1} Element(e) konnten nicht gelöscht werden',
'en' => '{1} item(s) could not be deleted',
'tr' => '{1} öğe silinemedi',
],
// ==================== CACHE-MELDUNGEN ====================
'cacheFolderCreated' => [
'de' => 'Cache-Ordner wurde angelegt ({1})',
'en' => 'Cache folder has been created ({1})',
'tr' => 'Önbellek klasörü oluşturuldu ({1})',
],
'cacheFolderFailed' => [
'de' => 'Cache-Ordner kann nicht erzeugt werden. Schreibrechte auf {1} benötigt',
'en' => 'Cache folder cannot be created. Write permissions on {1} required',
'tr' => 'Önbellek klasörü oluşturulamıyor. {1} üzerinde yazma izni gerekli',
],
// ==================== LOGIN/LOGOUT ====================
'logoutDemoNotPossible' => [
'de' => 'Logout in der Demo nicht möglich',
'en' => 'Logout not possible in demo mode',
'tr' => 'Demo modunda çıkış mümkün değil',
],
'logoutNotPossible' => [
'de' => 'Logout nicht möglich da per Config an',
'en' => 'Logout not possible because auto-login is enabled',
'tr' => 'Otomatik giriş aktif olduğu için çıkış mümkün değil',
],
'loginDemoNotPossible' => [
'de' => 'Login in der Demo nicht möglich',
'en' => 'Login not possible in demo mode',
'tr' => 'Demo modunda giriş mümkün değil',
],
'wrongPassword' => [
'de' => 'Falsches Passwort',
'en' => 'Wrong password',
'tr' => 'Yanlış şifre',
],
// ==================== UPLOAD-BERECHTIGUNGEN ====================
'uploadDemoNotPossible' => [
'de' => 'Upload in der Demo nicht möglich',
'en' => 'Upload not possible in demo mode',
'tr' => 'Demo modunda yükleme mümkün değil',
],
'uploadLoginRequired' => [
'de' => 'Upload nur im eingeloggtem Zustand möglich',
'en' => 'Upload only possible when logged in',
'tr' => 'Yükleme sadece giriş yapıldığında mümkün',
],
'uploadsDisabled' => [
'de' => 'Uploads sind ausgestellt',
'en' => 'Uploads are disabled',
'tr' => 'Yüklemeler devre dışı',
],
'invalidFolderName' => [
'de' => 'Ungültiger Ordnername',
'en' => 'Invalid folder name',
'tr' => 'Geçersiz klasör adı',
],
'invalidTargetPath' => [
'de' => 'Ungültiger Zielpfad',
'en' => 'Invalid target path',
'tr' => 'Geçersiz hedef yol',
],
'uploadWritePermission' => [
'de' => 'Kann nicht hochgeladen werden. Schreibrechte benötigt.',
'en' => 'Cannot upload. Write permissions required.',
'tr' => 'Yüklenemiyor. Yazma izni gerekli.',
],
// ==================== DELETE-BERECHTIGUNGEN ====================
'deleteDemoNotPossible' => [
'de' => 'Löschen in der Demo nicht möglich',
'en' => 'Delete not possible in demo mode',
'tr' => 'Demo modunda silme mümkün değil',
],
'deleteLoginRequired' => [
'de' => 'Löschen nur im eingeloggtem Zustand möglich',
'en' => 'Delete only possible when logged in',
'tr' => 'Silme sadece giriş yapıldığında mümkün',
],
'deleteDisabled' => [
'de' => 'Löschen sind ausgestellt',
'en' => 'Delete is disabled',
'tr' => 'Silme devre dışı',
],
'invalidPathWithName' => [
'de' => 'Ungültiger Pfad: {1}',
'en' => 'Invalid path: {1}',
'tr' => 'Geçersiz yol: {1}',
],
'cannotDeleteWrite' => [
'de' => 'Kann nicht gelöscht werden. Schreibrechte benötigt',
'en' => 'Cannot delete. Write permissions required',
'tr' => 'Silinemiyor. Yazma izni gerekli',
],
'noElementsSelected' => [
'de' => 'Keine Elemente ausgewählt',
'en' => 'No elements selected',
'tr' => 'Hiç öğe seçilmedi',
],
// ==================== CSRF & SICHERHEIT ====================
'csrfError' => [
'de' => 'Sicherheitstoken ungültig. Bitte Seite neu laden und erneut versuchen.',
'en' => 'Invalid security token. Please reload the page and try again.',
'tr' => 'Geçersiz güvenlik jetonu. Lütfen sayfayı yenileyin ve tekrar deneyin.',
],
// ==================== UPLOAD FEHLERMELDUNGEN (PHP Upload Errors) ====================
'uploadErrorIniSize' => [
'de' => 'Datei überschreitet upload_max_filesize',
'en' => 'File exceeds upload_max_filesize',
'tr' => 'Dosya upload_max_filesize sınırını aşıyor',
],
'uploadErrorFormSize' => [
'de' => 'Datei überschreitet MAX_FILE_SIZE',
'en' => 'File exceeds MAX_FILE_SIZE',
'tr' => 'Dosya MAX_FILE_SIZE sınırını aşıyor',
],
'uploadErrorPartial' => [
'de' => 'Datei wurde nur teilweise hochgeladen',
'en' => 'File was only partially uploaded',
'tr' => 'Dosya yalnızca kısmen yüklendi',
],
'uploadErrorNoFile' => [
'de' => 'Keine Datei hochgeladen',
'en' => 'No file uploaded',
'tr' => 'Dosya yüklenmedi',
],
'uploadErrorNoTmpDir' => [
'de' => 'Temporäres Verzeichnis fehlt',
'en' => 'Temporary directory missing',
'tr' => 'Geçici dizin eksik',
],
'uploadErrorCantWrite' => [
'de' => 'Datei konnte nicht gespeichert werden',
'en' => 'File could not be saved',
'tr' => 'Dosya kaydedilemedi',
],
'uploadErrorExtension' => [
'de' => 'Upload durch Erweiterung gestoppt',
'en' => 'Upload stopped by extension',
'tr' => 'Yükleme eklenti tarafından durduruldu',
],
'uploadErrorUnknown' => [
'de' => 'Unbekannter Upload-Fehler (Code: {1})',
'en' => 'Unknown upload error (Code: {1})',
'tr' => 'Bilinmeyen yükleme hatası (Kod: {1})',
],
// ==================== MIME-VALIDIERUNG ====================
'fileMimeMismatch' => [
'de' => 'Die Datei "{1}" entspricht nicht dem erwarteten Dateityp (erkannt: {2})',
'en' => 'The file "{1}" does not match the expected file type (detected: {2})',
'tr' => '"{1}" dosyası beklenen dosya türüyle eşleşmiyor (algılanan: {2})',
],
];
########################### SPRACHE SETZEN & ABFRAGEN
##### SETZT ODER GIBT DIE AKTUELLE SPRACHE ZURÜCK
public static function language(?string $set = null): string
{
if ($set !== null && in_array($set, self::$languageList, true)) {
self::$language = $set;
}
return self::$language;
}
##### GIBT DIE LISTE DER UNTERSTÜTZTEN SPRACHEN ZURÜCK
public static function languageList(): array
{
return self::$languageList;
}
########################### JAVASCRIPT-KONVERTIERUNG
##### KONVERTIERT EIN ÜBERSETZUNGS-ARRAY IN EIN JAVASCRIPT-OBJEKT
public static function convertListToJs(array $list): string
{
$return = [];
$return[] = str_repeat(' ', 8) . 'fvLang = {';
foreach ($list as $k => $v) {
$return[] = str_repeat(' ', 12) . str_pad((string)$k, 24) . ': \'' . addslashes((string)$v) . '\',';
}
$return[] = str_repeat(' ', 8) . '};';
return implode(PHP_EOL, $return);
}
########################### ÜBERSETZUNG ABRUFEN
##### HOLT EINE ÜBERSETZUNG MIT OPTIONALEN PLACEHOLDERN
public static function get(string $key): string
{
$key    = trim($key);
$return = '{' . $key . '}';
//-- Prüfen ob Key existiert
if (isset(self::$langList[$key])) {
$elem = self::$langList[$key];
} else {
$elem = false;
}
if ($elem && isset($elem[self::$language])) {
//-- Key gefunden – loggen
$return = $elem[self::$language];
}
//-- Platzhalter {1}, {2}, ... ersetzen
$numargs  = func_num_args();
$arg_list = func_get_args();
for ($i = 1; $i < $numargs; $i++) {
if ($i > 0) {
$return = str_replace('{' . $i . '}', $arg_list[$i], $return);
}
}
return $return;
}
##### GIBT ALLE ÜBERSETZUNGEN DER AKTUELLEN SPRACHE ZURÜCK
public static function getAll(): array
{
$result = [];
foreach (self::$langList as $key => $translations) {
$result[$key] = $translations[self::$language] ?? '{' . $key . '}';
}
return $result;
}
##### GIBT EINE UPLOAD-FEHLERMELDUNG ANHAND DES ERROR-CODES ZURÜCK
public static function getUploadErrorMessage(int $errorCode, string $fileName = ''): string
{
//-- Mapping der PHP-Upload-Fehlercodes zu Übersetzungs-Keys
switch ($errorCode) {
case UPLOAD_ERR_INI_SIZE:
$key = 'uploadErrorIniSize';
break;
case UPLOAD_ERR_FORM_SIZE:
$key = 'uploadErrorFormSize';
break;
case UPLOAD_ERR_PARTIAL:
$key = 'uploadErrorPartial';
break;
case UPLOAD_ERR_NO_FILE:
$key = 'uploadErrorNoFile';
break;
case UPLOAD_ERR_NO_TMP_DIR:
$key = 'uploadErrorNoTmpDir';
break;
case UPLOAD_ERR_CANT_WRITE:
$key = 'uploadErrorCantWrite';
break;
case UPLOAD_ERR_EXTENSION:
$key = 'uploadErrorExtension';
break;
default:
$key = 'uploadErrorUnknown';
break;
}
if ($key === 'uploadErrorUnknown') {
//-- Unbekannter Fehler mit Code
return self::get($key, (string)$errorCode);
}
return self::get($key);
}
}
?>
<?php
/**
* FV_Image – Leichtgewichtige Bildbearbeitungs- und Caching-Klasse
* ===================================================================
*
* Diese Klasse bietet eine schlanke Schnittstelle zur Bildverarbeitung.
* Sie unterstützt das Laden, Skalieren, Zuschneiden, Drehen und Cachen
* von Bildern in verschiedenen Formaten (JPEG, PNG, GIF, WebP).
*
*
* UNTERSTÜTZTE MODI (MODUS)
* --------------------------
*   - l:   Längste Seite wird auf size gesetzt (proportional)
*   - s:   Kürzeste Seite wird auf size gesetzt (proportional)
*   - w:   Breite wird auf size gesetzt (Höhe proportional)
*   - h:   Höhe wird auf size gesetzt (Breite proportional)
*   - p:   Pixelanzahl wird auf size gesetzt
*   - q:   Quadratisch (1:1), kürzeste Seite auf size
*   - kp:  Kilopixel (size * 1000 Pixel)
*   - mp:  Megapixel (size * 1.000.000 Pixel)
*
*
* CACHING & BOOSTING
* ------------------
*   - Cache:        Zwischenspeicherung verarbeiteter Bilder im Dateisystem
*   - Boost:        Mehrstufige Vorskalierung für schnellere Thumbnails
*   - Qualität:     Dynamische oder statische JPEG/WebP-Komprimierung
*
*
* EXIF-UNTERSTÜTZUNG
* ------------------
*   - Liest Kameradaten, Belichtung, ISO, Blende, GPS
*   - Automatische Rotation anhand Orientation-Tag (optional)
*
*
* FEHLERBEHANDLUNG
* -----------------
*   - Bei Fehlern wird ein internes Platzhalter-Bild generiert
*   - Fehlerliste kann über getErrorList() abgerufen werden
*
*
* VERWENDUNG
* ----------
*   $img = new FV_Image();
*   $img->imageFile('/pfad/zum/bild.jpg')
*       ->modus('l')
*       ->size(800)
*       ->quality(85)
*       ->cache(true)
*       ->get();
*
*   // Data-URL für Benachrichtigung
*   $dataUrl = $img->imageFile('/pfad/bild.jpg')
*       ->modus('q')
*       ->size(20)
*       ->get('inline');
*
* =====================================================================
*/
class FV_Image
{
########################### EIGENSCHAFTEN (BILDPARAMETER)
##### AUSGABE-PARAMETER
private ?string $name           = null;
private ?string $modus          = null;
private ?int $size              = null;
private ?int $fileSize          = null;
private ?float $ratio           = null;
private ?int $scale             = null;
private ?int $quality           = null;
private string $qualityModus    = 'dynamic';
private ?string $extension      = null;
private ?bool $doSharp          = null;
private bool $doDownload        = false;
private bool $doCache           = false;
private bool $doBoost           = false;
private bool $ratioExchangable  = true;
private ?string $cacheDir       = null;
private $boostFactor            = false;      // bool|int - gemischt, daher kein Typ
private ?string $boostExtension = 'webp';
private $cachedLocation         = null;       // string|false - gemischt
private $bgId                   = false;      // int|false - gemischt
private $canvas                 = null;       // resource - kein Typ möglich
private $useErrorImg            = false;      // bool|string - gemischt
private string $errorBgHex      = '7c1c30';
private ?int $width             = null;
private ?int $height            = null;
private string $returnType      = 'image';
##### QUELLBILD-PARAMETER
private ?string $srcoImgFile   = null;
private ?string $srcoExtension = null;
private ?int $srcoWidth        = null;
private ?int $srcoHeight       = null;
private ?int $srcoFileSize     = null;
private $srcCanvas             = null;   // resource - kein Typ möglich
private ?int $srcWidth         = null;
private ?int $srcHeight        = null;
private bool $getError         = false;
private array $errorList       = [];
private int $rotateDeg         = 0;
##### STATISCHE MIME-MAPPING
private static array $intToMime = [
2  => 'jpg',
3  => 'png',
18 => 'webp',
1  => 'gif',
];
########################### KONSTRUKTOR & INITIALISIERUNG
##### KONSTRUKTOR: PRÜFT WEBP-VERFÜGBARKEIT
public function __construct($param = [])
{
//-- WebP-Unterstützung prüfen
if ($this->boostExtension == 'webp' && !function_exists('imagewebp')) {
$this->boostExtension = null;
}
}
########################### FLUENT-INTERFACE: EINSTELLUNGEN
##### DREHT DAS BILD UM 90°, 180° ODER 270°
public function rotate($do = null): self
{
if ($do === false or $do === 0) {
$this->rotateDeg = 0;
} else if ((is_int($do) or is_float($do)) and $do > 0 and $do < 1000) {
$i  = 1;
$do = (int) $do;
//-- Mehrfachrotation ausführen
while ($i <= $do) {
$this->rotate();
$i++;
}
} else {
//-- Rotation um 90° (negativ)
$this->rotateDeg = $this->rotateDeg - 90;
if ($this->rotateDeg < 0) {
$this->rotateDeg = $this->rotateDeg + 360;
}
}
//-- Abmessungen zurücksetzen, damit sie neu berechnet werden
$this->width     = null;
$this->height    = null;
$this->srcWidth  = null;
$this->srcHeight = null;
return $this;
}
##### STATISCHE METHODE: LIEST EXIF-DATEN AUS EINER DATEI
public static function getExifInfo(string $file): array
{
//-- EXIF-Daten lesen (stille Fehlerunterdrückung)
$exif = @exif_read_data($file, 'EXIF', true);
$info = [
'camBrand'     => null,
'camModel'     => null,
'exposureTime' => null,
'fNumber'      => null,
'iso'          => null,
'dateTime'     => null,
'focalLength'  => null,
'orientation'  => null,
'rotationDeg'  => null,
'shouldRotate' => null,
'gpsLatitude'  => null,
'gpsLongitude' => null,
'gpsAltitude'  => null,
];
if (!$exif) {
return $info;
}
//-- Kamera-Marke und Modell
$info['camBrand'] = $exif['IFD0']['Make'] ?? null;
$info['camModel'] = $exif['IFD0']['Model'] ?? null;
//-- Belichtungszeit
if (!empty($exif['EXIF']['ExposureTime'])) {
$list = explode('/', $exif['EXIF']['ExposureTime']);
$info['exposureTime'] = (count($list) == 2 && $list[0] > 0) ? '1/' . round($list[1] / $list[0]) : $exif['EXIF']['ExposureTime'];
}
//-- Blende
if (!empty($exif['EXIF']['FNumber'])) {
$list = explode('/', $exif['EXIF']['FNumber']);
$info['fNumber'] = (count($list) == 2 && $list[1] > 0) ? $list[0] / $list[1] : $exif['EXIF']['FNumber'];
}
$info['iso'] = $exif['EXIF']['ISOSpeedRatings'] ?? null;
//-- Aufnahmedatum
if (!empty($exif['EXIF']['DateTimeOriginal'])) {
$info['dateTime'] = strtotime($exif['EXIF']['DateTimeOriginal']);
} elseif (!empty($exif['IFD0']['DateTime'])) {
$info['dateTime'] = strtotime($exif['IFD0']['DateTime']);
}
//-- Brennweite
if (!empty($exif['EXIF']['FocalLength'])) {
$list = explode('/', $exif['EXIF']['FocalLength']);
$info['focalLength'] = (count($list) == 2 && $list[1] > 0) ? round($list[0] / $list[1], 1) . ' mm' : $exif['EXIF']['FocalLength'] . ' mm';
}
//-- Orientierung (für Rotation)
if (isset($exif['IFD0']['Orientation'])) {
$info['orientation'] = (int)$exif['IFD0']['Orientation'];
switch ($info['orientation']) {
case 3:  $info['rotationDeg'] = 180; break;
case 6:  $info['rotationDeg'] = 90;  break;
case 8:  $info['rotationDeg'] = 270; break;
default: $info['rotationDeg'] = 0;
}
$info['shouldRotate'] = ($info['orientation'] > 1);
}
//-- GPS-Koordinaten
if (!empty($exif['GPS']['GPSLatitude'])) {
$latArr = $exif['GPS']['GPSLatitude'];
$latRef = $exif['GPS']['GPSLatitudeRef'] ?? 'N';
$latDeg = (count($latArr) >= 1) ? (float)($latArr[0] ?? 0) : 0;
$latMin = (count($latArr) >= 2) ? (float)($latArr[1] ?? 0) / 60 : 0;
$latSec = (count($latArr) >= 3) ? (float)($latArr[2] ?? 0) / 3600 : 0;
$info['gpsLatitude'] = ($latRef == 'S' ? -1 : 1) * ($latDeg + $latMin + $latSec);
}
if (!empty($exif['GPS']['GPSLongitude'])) {
$lonArr = $exif['GPS']['GPSLongitude'];
$lonRef = $exif['GPS']['GPSLongitudeRef'] ?? 'E';
$lonDeg = (count($lonArr) >= 1) ? (float)($lonArr[0] ?? 0) : 0;
$lonMin = (count($lonArr) >= 2) ? (float)($lonArr[1] ?? 0) / 60 : 0;
$lonSec = (count($lonArr) >= 3) ? (float)($lonArr[2] ?? 0) / 3600 : 0;
$info['gpsLongitude'] = ($lonRef == 'W' ? -1 : 1) * ($lonDeg + $lonMin + $lonSec);
}
if (!empty($exif['GPS']['GPSAltitude'])) {
$altParts = explode('/', $exif['GPS']['GPSAltitude']);
$info['gpsAltitude'] = (count($altParts) == 2 && $altParts[1] > 0) ? $altParts[0] / $altParts[1] : (float)$exif['GPS']['GPSAltitude'];
}
return $info;
}
##### AKTIVIERT/DEAKTIVIERT CACHING
public function cache($do = null): self
{
$this->doCache = ($do !== false);
return $this;
}
##### AKTIVIERT/DEAKTIVIERT DOWNLOAD-HEADER
public function download($do = null): self
{
$this->doDownload = ($do !== false);
return $this;
}
##### AKTIVIERT/DEAKTIVIERT BOOSTING (VORSKALIERUNG)
public function boost($do = null, $ext = null): self
{
//-- Boosting-Format setzen
if (!is_null($ext)) {
if (is_string($ext) && preg_match('#^(png|webp)$#', $ext)) {
if (!($ext == 'webp' && !function_exists('imagewebp'))) {
$this->boostExtension = strtolower($ext);
}
} elseif ($ext === false || is_null($ext)) {
$this->boostExtension = null;
} else {
$this->addError('boostExtension');
}
}
$this->doBoost = ($do !== false);
return $this;
}
##### SETZT DAS AUSGABE-FORMAT (JPG, PNG, GIF, WEBP)
public function extension($ext): self
{
if (is_string($ext) && preg_match('#^(jpg|png|gif|webp)$#', $ext)) {
if (!($ext == 'webp' && !function_exists('imagewebp'))) {
$this->extension = strtolower($ext);
}
} elseif ($ext === false || is_null($ext)) {
$this->extension = null;
} else {
$this->addError('extension');
}
return $this;
}
##### SETZT DEN VERARBEITUNGSMODUS (L, S, W, H, P, Q, KP, MP)
public function modus($modus): self
{
if (is_string($modus) && preg_match('#^(l|s|w|h|q|p|kp|mp)$#', $modus)) {
$this->modus = $modus;
} else {
$this->addError('modus');
$this->modus = null;
}
return $this;
}
##### SETZT DEN AUSGABE-DATEINAMEN
public function name($name): self
{
$this->name = is_string($name) ? trim($name) : null;
return $this;
}
##### SETZT DAS SEITENVERHÄLTNIS (RATIO)
public function ratio($ratio, $exchangable = true): self
{
if ($parsed = self::numberSplitter($ratio)) {
$this->ratio = $parsed['ratio'];
} else {
$this->ratio = null;
}
if ($exchangable === true || $exchangable === false) {
$this->ratioExchangable = $exchangable;
}
return $this;
}
##### SETZT DEN SKALIERUNGSFAKTOR (1-100%)
public function scale($do = null): self
{
if (is_int($do) && $do > 0 && $do <= 100) {
$this->scale = $do;
} else {
$this->scale = null;
}
return $this;
}
##### AKTIVIERT/DEAKTIVIERT SCHÄRFUNG
public function sharp($do = null): self
{
$this->doSharp = ($do !== false);
return $this;
}
##### SETZT DIE ZIELGRÖSSE (PIXEL, KP, MP)
public function size($size): self
{
if ((is_int($size) || is_float($size)) && $size > 0) {
$this->size = (int) $size;
} else {
$this->size = null;
}
return $this;
}
##### DEAKTIVIERT EINE BESTIMMTE AKTION
public function undo(string $action): self
{
switch ($action) {
case 'download': $this->download(false); break;
case 'cache':    $this->cache(false);    break;
case 'sharp':    $this->sharp(false);    break;
case 'boost':    $this->boost(false);    break;
case 'rotate':   $this->rotate(false);   break;
}
return $this;
}
##### AKTIVIERT EINE BESTIMMTE AKTION
public function do(string $action): self
{
switch ($action) {
case 'download': $this->download(); break;
case 'cache':    $this->cache();    break;
case 'sharp':    $this->sharp();    break;
case 'boost':    $this->boost();    break;
case 'rotate':   $this->rotate();   break;
}
return $this;
}
##### SETZT DIE BILDQUALITÄT (1-100) UND MODUS
public function quality($perc, $modus = null): self
{
if ((is_int($perc) || is_float($perc)) && $perc > 0 && $perc <= 100) {
$this->quality = (int) $perc;
} else {
$this->addError('quality');
$this->quality = null;
$this->qualityModus = 'dynamic';
}
if (!is_null($modus)) {
if (is_string($modus) && preg_match('#^(dynamic|static)$#', $modus)) {
$this->qualityModus = $modus;
} else {
$this->addError('qualityModus');
}
}
return $this;
}
########################### HAUPTMETHODE: BILDVERARBEITUNG
##### FÜHRT DIE BILDVERARBEITUNG AUS UND GIBT DAS ERGEBNIS ZURÜCK
public function get($returnType = 'image', $getError = false)
{
if ($getError === true) {
$this->getError = true;
}
$this->setReturnType($returnType);
$this->initSrcInfos();
$this->initInfos();
//-- Bei Fehlerbild Cache und Boosting deaktivieren
if ($this->useErrorImg) {
$this->doCache = false;
$this->doBoost = false;
}
//-- Cache-Verzeichnis vorbereiten
if ($this->doCache || $this->doBoost) {
if (!FV_FileSystem::createDir($this->cacheDir)) {
$this->doCache = false;
$this->doBoost = false;
$this->addError('cacheDirCreate');
}
if (!is_writable($this->cacheDir)) {
$this->doCache = false;
$this->doBoost = false;
$this->addError('cacheDirWrite');
}
if ($this->getSrcoWidth() * $this->getSrcoHeight() < (400 * 400)) {
$this->doBoost = false;
}
if ($this->doBoost) {
foreach ($this->getBoostFactorList() as $factor) {
if ($this->icrPosXLength() * $this->icrPosYLength() * 4 <= $this->icrSrcPosXLength() * $this->icrSrcPosYLength() / ($factor * $factor)) {
$this->boostFactor = $factor;
}
}
if (!$this->boostFactor) {
$this->doBoost = false;
}
}
}
//-- Info-Modus: nur Dimensionen zurückgeben
if ($this->getReturnType() == 'info') {
return [
'width'     => $this->getWidth(),
'height'    => $this->getHeight(),
'extension' => $this->getExtension(),
];
}
//-- Cache verwenden, wenn vorhanden
if ($this->doCache && in_array($this->getReturnType(), ['image', 'content', 'inline']) && $this->getSrcoLocation() && $this->getCachedLocation()) {
switch ($this->getReturnType()) {
case 'image':
if (!$this->getError) {
$this->setHeader();
}
$content = file_get_contents($this->getCachedLocation());
break;
case 'content':
$content = file_get_contents($this->getCachedLocation());
break;
case 'inline':
$mime = 'image/' . $this->getExtension();
if ($this->getExtension() === 'svg') {
$mime = 'image/svg+xml';
}
$content = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($this->getCachedLocation()));
break;
}
} else {
//-- Bild neu generieren
$this->createBoosting();
$this->createCanvas();
$this->setSrcCanvasToCanvas();
$this->setCanvasSharpness();
$this->setCanvasInterlace();
$content = $this->getImage();
}
if ($this->getError) {
return $this->getErrorList();
}
if ($this->getReturnType() == 'image') {
echo $content;
die();
}
return $content;
}
########################### CACHE-METHODEN
##### SETZT DAS CACHE-VERZEICHNIS
public function cacheDir($location = null): self
{
if ($location === false) {
$this->cacheDir = null;
} elseif (is_string($location) && preg_match('#/$#', $location)) {
$this->cacheDir = FV_Helper::slashBeautifier($location);
} else {
$this->addError('cacheDir');
}
return $this;
}
##### SETZT DIE QUELLBILD-DATEI
public function imageFile($imgLocation = null): self
{
if ($imgLocation === false) {
$this->srcoImgFile = null;
} else {
$found = false;
if (is_string($imgLocation)) {
$found = $imgLocation;
}
if ($found && is_null($this->srcoImgFile) && file_exists($found)) {
$this->srcoImgFile = trim($found);
}
if (is_null($this->srcoImgFile)) {
$this->addError('imageFile');
}
}
return $this;
}
##### GIBT DAS VERARBEITETE BILD ALS STRING ZURÜCK
private function getImage()
{
$saveLoc = $this->doCache ? $this->getCachingLocation() : false;
switch ($this->getReturnType()) {
case 'content':
return $this->imageXXX($this->getCanvas(), $this->getExtension(), $this->getExtensionQuality(), $saveLoc, true);
case 'inline':
$string = $this->imageXXX($this->getCanvas(), $this->getExtension(), $this->getExtensionQuality(), $saveLoc, true);
$mime = 'image/' . $this->getExtension();
if ($this->getExtension() === 'svg') {
$mime = 'image/svg+xml';
}
return 'data:' . $mime . ';base64,' . base64_encode($string);
case 'image':
$fileString = $this->imageXXX($this->getCanvas(), $this->getExtension(), $this->getExtensionQuality(), $saveLoc, true);
$this->fileSize = strlen($fileString);
if (!$this->getError) {
$this->setHeader();
}
return $fileString;
}
imagedestroy($this->canvas);
}
##### SETZT DEN RÜCKGABE-TYP
private function setReturnType(string $returnType): void
{
if (in_array($returnType, ['image', 'content', 'inline', 'info'])) {
$this->returnType = $returnType;
}
}
##### GIBT DEN RÜCKGABE-TYP ZURÜCK
private function getReturnType(): string
{
return $this->returnType;
}
##### INITIALISIERT BILDINFORMATIONEN (GRÖSSE, MODUS, RATIO)
private function initInfos(): void
{
if (!is_null($this->width)) return;
//-- Standard-Ratio aus Quellbild
if (is_null($this->ratio)) {
$this->ratio = $this->getSrcWidth() / $this->getSrcHeight();
}
//-- Modus automatisch setzen, falls nicht definiert
if (is_null($this->modus)) {
if (is_null($this->size)) {
if ($this->getSrcWidth() > $this->getSrcHeight()) {
$this->modus  = 'l';
$this->size   = $this->getSrcWidth();
} else {
$this->modus  = 'l';
$this->size   = $this->getSrcHeight();
}
} else {
$this->modus  = 'l';
}
} elseif ($this->modus == 'q') {
$this->modus  = 'l';
$this->ratio  = 1;
} elseif (in_array($this->modus, ['p', 'kp', 'mp']) && !is_null($this->size)) {
$mult = 1;
if ($this->modus == 'kp') {
$mult = 1000;
} elseif ($this->modus == 'mp') {
$mult = 1000000;
}
$info = self::calcDimension([
'targetPixelCount'    => $this->size * $mult,
'targetRatio'         => $this->ratio,
]);
$this->modus  = 'p';
$this->size   = $info['calculatedWidth'] * $info['calculatedHeight'];
}
//-- Ratio tauschen, wenn Seitenverhältnis wechselt
if ($this->ratioExchangable && in_array($this->getModus(), ['l', 's', 'p'])) {
$targetPos = ($this->ratio > 1) ? 'landscape' : 'portrait';
$srcPos    = ($this->getSrcWidth() / $this->getSrcHeight() > 1) ? 'landscape' : 'portrait';
if ($targetPos != $srcPos) {
$this->ratio = 1 / $this->ratio;
}
}
//-- Optimalen Zuschnitt berechnen
$info = self::calcOptimalClipping([
'w'           => $this->getSrcWidth(),
'h'           => $this->getSrcHeight(),
'targetRatio' => $this->ratio,
]);
$srcMaxPossibleWB = $info['calculatedWidth'];
$srcMaxPossibleHB = $info['calculatedHeight'];
$srcMaxPossibleMP = $srcMaxPossibleWB * $srcMaxPossibleHB;
//-- Modus umwandeln (l/s zu w/h)
if ($this->modus == 'l') {
$this->modus = ($this->ratio > 1) ? 'w' : 'h';
} elseif ($this->modus == 's') {
$this->modus = ($this->ratio > 1) ? 'h' : 'w';
}
if ($this->modus == 'p') {
$size = min($this->size, $srcMaxPossibleMP);
} elseif ($this->modus == 'w') {
$size = min($this->size, $srcMaxPossibleWB);
} elseif ($this->modus == 'h') {
$size = min($this->size, $srcMaxPossibleHB);
} else {
$size = $this->size;
}
$this->size = round($size);
$dim = self::calcHWbyMSR(['modus' => $this->modus, 'size' => $this->size, 'ratio' => $this->ratio]);
$this->width = self::calcScaledValue(['scale' => $this->getScale(), 'size' => $dim['calculatedWidth']]);
$this->height = self::calcScaledValue(['scale' => $this->getScale(), 'size' => $dim['calculatedHeight']]);
}
##### SETZT HTTP-HEADER FÜR BILD-AUSGABE
private function setHeader(): void
{
if ($this->getReturnType() == 'image' && !$this->getError) {
$disposition = $this->doDownload ? 'attachment' : 'inline';
if ($this->getFileSize()) {
header('Content-Length: ' . $this->getFileSize());
}
if ($this->useErrorImg) {
header('HTTP/1.0 404 Not Found');
}
if ($this->getSrcoLocation()) {
header('Content-Disposition: ' . $disposition . '; filename="' . $this->getName(true) . '.' . $this->getExtension() . '"');
}
switch ($this->getExtension()) {
case 'gif':  header('Content-Type: image/gif'); break;
case 'jpg':  header('Content-Type: image/jpeg'); break;
case 'png':  header('Content-Type: image/png'); break;
case 'webp': header('Content-Type: image/webp'); break;
}
}
}
########################### FEHLERBEHANDLUNG
##### FÜGT EINEN FEHLER ZUR FEHLERLISTE HINZU
private function addError(string $key, $identifier = ''): void
{
$this->errorList[$key][] = $identifier ?: true;
}
##### GIBT DIE FEHLERLISTE ZURÜCK
public function getErrorList(): array
{
$e = [
'boostExtension' =>  '->boost(true, png | webp | false):string/bool',
'extension' =>       '->extension(jpg | png | gif | webp):string',
'modus' =>           '->modus(l | s | w | h | q | p | kp | mp):string',
'quality' =>         '->quality(1-100):int',
'qualityModus' =>    '->quality(int, dynamic | static):string',
'cacheDirCreate' =>  'Cacheordner konnte nicht erstellt werden',
'cacheDirWrite' =>   'Cacheordner ist nicht beschreibbar',
'cacheDir' =>        'cacheDir(huhu/cachedir/):string',
'imageFile' =>       '->imageFile(/srv/hh/img.jpg):string',
'srcoExtension' =>   'Bildtyp konnte nicht ermittelt werden',
'srcoImgFile' =>     'Bild konnte nicht gefunden werden',
'getSrcoLocation' => 'Bildpfad nicht gefunden',
'createCanvas' =>    'Konnte Bildzeichnung nicht erstellen',
'setSrcCanvasToCanvas' =>'Konnte Bildzeichnung nicht übernehmen',
'boostingSave' =>    'Boostbild konnte nicht gespeichert werden',
'boostingCopy' =>    'Boostbild konnte nicht kopiert werden',
'boostingCreate' =>  'Boostbild konnte nicht erstellt werden',
];
$return = [];
if (count($this->errorList)) {
foreach ($this->errorList as $keyOfEr => $list) {
$identStr   = '';
$ident      = false;
$identCount = 0;
foreach ($list as $val) {
if ($val !== true) {
$identCount++;
$ident = (string) $val;
}
}
if ($ident) {
$identStr = ($identCount > 1) ? ', z.B.: "' . $ident . '"' : ' => "' . $ident . '"';
}
$countString = (count($list) > 1) ? ' (' . count($list) . ' Vorkommnisse)' : '';
$return[$keyOfEr] = ($e[$keyOfEr] ?? $keyOfEr) . $identStr . $countString;
}
}
return $return;
}
##### GIBT DIE FEHLERLISTE AUS
public function printErrorList(): void
{
print_r($this->getErrorList());
}
########################### GETTER / HILFSMETHODEN
##### GIBT DEN SKALIERUNGSFAKTOR ZURÜCK
private function getScale(): ?int
{
return $this->scale ?? 1;
}
##### GIBT DAS AUSGABE-FORMAT ZURÜCK
public function getExtension(): string
{
$ext = $this->extension ?? $this->getSrcoExtension();
return $ext == 'jpeg' ? 'jpg' : $ext;
}
##### GIBT DIE DATEIGRÖSSE ZURÜCK
private function getFileSize()
{
if (is_null($this->fileSize)) {
if ($this->getCachedLocation()) {
$this->fileSize = filesize($this->getCachedLocation());
} else {
$this->fileSize = false;
}
}
return $this->fileSize;
}
##### GIBT DEN MODUS ZURÜCK
private function getModus(): string
{
return $this->modus ?? 'l';
}
##### GIBT DEN DATEINAMEN ZURÜCK
public function getName(bool $az = false): string
{
if ($this->useErrorImg) {
$name = 'error';
} elseif (!is_null($this->name)) {
$name = $this->name;
} else {
$arrName = self::pathInfo($this->getSrcoLocation());
$name = $arrName['filename'];
}
if ($az) {
$name = preg_replace('/[^a-z0-9_]+/', '-', strtolower($name));
$name = preg_replace('/-+/', '-', $name);
}
return trim($name);
}
##### GIBT DIE BREITE ZURÜCK
private function getWidth(): ?int
{
return $this->width;
}
##### GIBT DIE HÖHE ZURÜCK
private function getHeight(): ?int
{
return $this->height;
}
##### GIBT DIE MEGAPIXEL ZURÜCK
private function getMp(bool $inner = false): float
{
return $this->getWidth() * $this->getHeight() / 1000000;
}
##### GIBT DIE QUALITÄT FÜR DAS AUSGABE-FORMAT ZURÜCK
private function getExtensionQuality(): int
{
$ql = $this->quality ?? ($this->qualityModus == 'dynamic' ? 96 : 88);
$ext = $this->getExtension();
if ($ext == 'jpg' || $ext == 'webp') {
if ($this->qualityModus == 'dynamic') {
return (int) round(self::calcDynamicQualityBySize($ql, $this->getWidth() * $this->getHeight()));
}
return $ql;
}
if ($ext == 'png') {
return 6;
}
return $ql;
}
##### GIBT DIE ZIEL-CANVAS ZURÜCK
private function getCanvas()
{
if (is_null($this->canvas)) {
$this->createCanvas();
}
return $this->canvas;
}
##### GIBT DEN CACHE-DATEIPFAD ZURÜCK
private function getCachedLocation()
{
if (is_null($this->cachedLocation)) {
$file = $this->getCachingLocation();
$this->cachedLocation = file_exists($file) ? $file : false;
}
return $this->cachedLocation ?: false;
}
##### GIBT DEN ZIEL-PFAD FÜR CACHE-DATEI ZURÜCK
private function getCachingLocation(): string
{
return $this->cacheDir . $this->getCachingFilename();
}
##### ERZEUGT DEN CACHE-DATEINAMEN
private function getCachingFilename(): string
{
if (!$this->useErrorImg) {
$name = 'c_' . crc32($this->getSrcoLocation()) . '_' .
crc32(
$this->getSrcoLocation() . '|' .
$this->srcoFileSize . '|' .
$this->getSrcoExtension() . '|' .
$this->getSrcoWidth() . '|' .
$this->getSrcoHeight() . '|' .
$this->getRatio() . '|' .
$this->doSharp . '|' .
$this->getExtension() . '|' .
$this->getWidth() . '|' .
$this->getHeight() . '|' .
$this->getScale() . '|' .
$this->rotateDeg . '|' .
$this->getExtensionQuality()
) . '.' . $this->getExtension();
} else {
$name = 'errorcache';
}
return $name;
}
##### GIBT DEN BOOST-DATEIPFAD ZURÜCK
private function getBoostLocation($fac = false): string
{
$factor = $fac ?: $this->boostFactor;
return $this->cacheDir . $this->getBoostFilename($factor) . '.' . $this->getBoostExtension();
}
##### ERZEUGT DEN BOOST-DATEINAMEN
private function getBoostFilename($fac = false): string
{
$factor = $fac ?: $this->boostFactor;
return 'c_' . crc32($this->getSrcoLocation()) . '_boost' . str_pad($factor, 2, '0', STR_PAD_LEFT) . '_' .
crc32(
$this->getSrcoLocation() . '|' .
$this->srcoFileSize . '|' .
$this->getSrcoExtension() . '|' .
$this->getSrcoWidth() . '|' .
$this->getSrcoHeight()
);
}
##### GIBT DAS BOOST-FORMAT ZURÜCK
private function getBoostExtension(): string
{
$ext = $this->boostExtension ?? $this->getSrcoExtension();
if ($ext == 'webp' && !function_exists('imagewebp')) {
$ext = $this->getSrcoExtension();
}
return $ext == 'jpeg' ? 'jpg' : $ext;
}
##### GIBT DIE LISTE DER BOOST-FAKTOREN ZURÜCK
private function getBoostFactorList(): array
{
$factors = [];
$srcPixels = $this->getSrcoWidth() * $this->getSrcoHeight() / 1000000;
$i = 2;
$mp = 1;
while ($srcPixels > ($mp * 0.99 / 4)) {
$factors[] = $i;
$i *= 2;
$mp *= 4;
}
return $factors;
}
##### INITIALISIERT QUELLBILD-INFORMATIONEN
private function initSrcInfos(): void
{
if (!is_null($this->srcoExtension)) return;
if ($loc = $this->getSrcoLocation()) {
if ($info = @getimagesize($loc)) {
$this->srcoFileSize = filesize($loc);
if ($this->rotateDeg == 90 || $this->rotateDeg == 270) {
$this->srcWidth  = (int) $info[1];
$this->srcHeight = (int) $info[0];
} else {
$this->srcWidth  = (int) $info[0];
$this->srcHeight = (int) $info[1];
}
$this->srcoWidth  = (int) $info[0];
$this->srcoHeight = (int) $info[1];
switch ($info[2]) {
case 1:  $this->srcoExtension = 'gif'; break;
case 2:  $this->srcoExtension = 'jpg'; break;
case 3:  $this->srcoExtension = 'png'; break;
case 18: $this->srcoExtension = 'webp'; break;
}
}
}
if (is_null($this->srcoExtension)) {
$this->addError('srcoExtension');
$this->scale       = null;
$this->quality     = null;
$this->rotateDeg   = 0;
$this->useErrorImg = 'byPhp';
$this->srcWidth  = $this->srcoWidth  = 200;
$this->srcHeight = $this->srcoHeight = 200;
$this->srcoExtension = 'png';
if (!is_null($this->ratio)) {
$dim = self::calcDimension([
'targetPixelCount' => 200 * 200,
'targetRatio'      => $this->ratio,
]);
$this->srcWidth  = $this->srcoWidth  = (int) $dim['calculatedWidth'];
$this->srcHeight = $this->srcoHeight = (int) $dim['calculatedHeight'];
}
}
}
##### GIBT DEN QUELLBILD-PFAD ZURÜCK
private function getSrcoLocation()
{
if (is_null($this->srcoImgFile) || !file_exists($this->srcoImgFile)) {
$this->addError('srcoImgFile');
$this->useErrorImg = 'byPhp';
return false;
}
return $this->srcoImgFile;
}
##### GIBT DAS QUELLBILD-FORMAT ZURÜCK
private function getSrcoExtension(): string
{
return $this->srcoExtension;
}
##### GIBT DIE QUELLBILD-BREITE (NACH ROTATION) ZURÜCK
private function getSrcWidth(): int
{
return $this->srcWidth;
}
##### GIBT DIE QUELLBILD-HÖHE (NACH ROTATION) ZURÜCK
private function getSrcHeight(): int
{
return $this->srcHeight;
}
##### GIBT DAS SEITENVERHÄLTNIS DES ZIELBILDES ZURÜCK
private function getRatio(): float
{
return $this->getWidth() / $this->getHeight();
}
##### GIBT DIE URSPRÜNGLICHE QUELLBILD-BREITE ZURÜCK
private function getSrcoWidth(): int
{
return $this->srcoWidth;
}
##### GIBT DIE URSPRÜNGLICHE QUELLBILD-HÖHE ZURÜCK
private function getSrcoHeight(): int
{
return $this->srcoHeight;
}
##### GIBT DIE QUELL-CANVAS ZURÜCK
private function getSrcCanvas($boostVersion = false)
{
if (is_null($this->srcCanvas)) {
$loc = $this->getSrcoLocation();
if ($loc) {
$ext = $boostVersion ? $this->getBoostExtension() : $this->getSrcoExtension();
$file = $boostVersion ? $this->getBoostLocation() : $loc;
switch ($ext) {
case 'gif':  $this->srcCanvas = @imagecreatefromgif($file); break;
case 'jpg':  $this->srcCanvas = @imagecreatefromjpeg($file); break;
case 'png':  $this->srcCanvas = @imagecreatefrompng($file); break;
case 'webp': $this->srcCanvas = @imagecreatefromwebp($file); break;
}
} else {
$this->addError('getSrcoLocation');
$color = $this->hexToRgb($this->errorBgHex);
$this->srcCanvas = imagecreatetruecolor($this->getSrcWidth(), $this->getSrcHeight());
$red = imagecolorallocate($this->srcCanvas, $color['r'], $color['g'], $color['b']);
imagefill($this->srcCanvas, 0, 0, $red);
$white = imagecolorallocate($this->srcCanvas, 255, 255, 255);
imagestring($this->srcCanvas, 5, $this->getSrcWidth() - 30, $this->getSrcHeight() - 30, 'X', $white);
}
if ($this->rotateDeg == 90 || $this->rotateDeg == 180 || $this->rotateDeg == 270) {
$this->srcCanvas = imagerotate($this->srcCanvas, $this->rotateDeg, 0);
}
}
return $this->srcCanvas;
}
##### KONVERTIERT HEX-FARBE IN RGB
private function hexToRgb(string $hex): array
{
if (strlen($hex) == 3) {
$r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
$g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
$b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
} else {
$r = hexdec(substr($hex, 0, 2));
$g = hexdec(substr($hex, 2, 2));
$b = hexdec(substr($hex, 4, 2));
}
return ['r' => $r, 'g' => $g, 'b' => $b];
}
##### POSITIONSBERECHNUNG: X-POSITION AUF ZIEL-CANVAS
private function icrPosX(): int
{
$pos = 0;
return (int) round($pos);
}
##### POSITIONSBERECHNUNG: Y-POSITION AUF ZIEL-CANVAS
private function icrPosY(): int
{
$pos = 0;
return (int) round($pos);
}
##### POSITIONSBERECHNUNG: BREITE AUF ZIEL-CANVAS
private function icrPosXLength(): int
{
$len = $this->getWidth();
return (int) round($len);
}
##### POSITIONSBERECHNUNG: HÖHE AUF ZIEL-CANVAS
private function icrPosYLength(): int
{
$len = $this->getHeight();
return (int) round($len);
}
##### POSITIONSBERECHNUNG: X-POSITION AUF QUELL-CANVAS
private function icrSrcPosX($boostVersion = false): int
{
$pos = 0;
$bv = $boostVersion ? (1 / $this->boostFactor) : 1;
$info = self::calcOptimalClipping([
'w'             => $this->getSrcWidth(),
'h'             => $this->getSrcHeight(),
'targetRatio'   => $this->getRatio(),
]);
$pos = $info['marginLeft'];
return (int) round($pos * $bv);
}
##### POSITIONSBERECHNUNG: Y-POSITION AUF QUELL-CANVAS
private function icrSrcPosY($boostVersion = false): int
{
$pos = 0;
$bv = $boostVersion ? (1 / $this->boostFactor) : 1;
$info = self::calcOptimalClipping([
'w'             => $this->getSrcWidth(),
'h'             => $this->getSrcHeight(),
'targetRatio'   => $this->getRatio(),
]);
$pos = $info['marginTop'];
return (int) round($pos * $bv);
}
##### POSITIONSBERECHNUNG: BREITE AUF QUELL-CANVAS
private function icrSrcPosXLength($boostVersion = false): int
{
$len = $this->getSrcWidth();
$bv = $boostVersion ? (1 / $this->boostFactor) : 1;
$info = self::calcOptimalClipping([
'w'           => $this->getSrcWidth(),
'h'           => $this->getSrcHeight(),
'targetRatio' => $this->getRatio(),
]);
$len = $this->getSrcWidth() - $info['marginX'];
return (int) round($len * $bv);
}
##### POSITIONSBERECHNUNG: HÖHE AUF QUELL-CANVAS
private function icrSrcPosYLength($boostVersion = false): int
{
$len = $this->getSrcHeight();
$bv = $boostVersion ? (1 / $this->boostFactor) : 1;
$info = self::calcOptimalClipping([
'w'           => $this->getSrcWidth(),
'h'           => $this->getSrcHeight(),
'targetRatio' => $this->getRatio(),
]);
$len = $this->getSrcHeight() - $info['marginY'];
return (int) round($len * $bv);
}
##### AKTIVIERT INTERLACING FÜR JPEG
private function setCanvasInterlace(): void
{
$do = ($this->getMp() > (100 * 100 / 1000000) && in_array($this->getExtension(), ['jpg']));
if ($do) {
imageinterlace($this->getCanvas(), self::isMinPhp('8.0') ? true : 1);
}
}
##### WENDET SCHÄRFUNG AUF DAS BILD AN
private function setCanvasSharpness(): void
{
$do = false;
if ($this->getMp(true) <= 3) {
$do = true;
}
if ($this->getWidth() == $this->getSrcWidth() || $this->getHeight() == $this->getSrcHeight()) {
$do = false;
}
if (in_array($this->getSrcoExtension(), ['gif', 'png']) && !in_array($this->getExtension(), ['jpg'])) {
$do = false;
}
if ($this->doSharp === false) {
$do = false;
}
if ($do) {
$shar = ($this->getMp(true) >= 1.5) ? 20 : 30;
$sharpen = [
[-1, -1, -1],
[-1, $shar, -1],
[-1, -1, -1],
];
$divisor = array_sum(array_map('array_sum', $sharpen));
imageconvolution($this->getCanvas(), $sharpen, $divisor, 0);
}
}
##### ERSTELLT DIE ZIEL-CANVAS
private function createCanvas(): void
{
$this->canvas = imagecreatetruecolor((int) $this->getWidth(), (int) $this->getHeight());
if ($this->canvas) {
$this->bgId = $this->afterCreateCanvas($this->canvas, $this->getExtension());
} else {
$this->addError('createCanvas');
}
}
##### NACHBEARBEITUNG NACH CANVAS-ERSTELLUNG
private function afterCreateCanvas(&$canvas, $ext)
{
$bgId = false;
if ($ext == 'jpg') {
$bgId = imagecolorallocate($canvas, 255, 255, 255);
imagefill($canvas, 0, 0, $bgId);
} elseif ($ext == 'gif') {
$bgId = imagecolorallocatealpha($canvas, 240, 240, 240, 60);
imagefill($canvas, 0, 0, $bgId);
} elseif (in_array($ext, ['png', 'webp'])) {
$bgId = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
imagefill($canvas, 0, 0, $bgId);
}
return $bgId;
}
##### KOPIERT DIE QUELL-CANVAS AUF DIE ZIEL-CANVAS
private function setSrcCanvasToCanvas(): void
{
if (@imagecopyresampled(
$this->getCanvas(),
$this->getSrcCanvas($this->doBoost),
$this->icrPosX(),
$this->icrPosY(),
$this->icrSrcPosX($this->doBoost),
$this->icrSrcPosY($this->doBoost),
$this->icrPosXLength(),
$this->icrPosYLength(),
$this->icrSrcPosXLength($this->doBoost),
$this->icrSrcPosYLength($this->doBoost)
)) {
imagedestroy($this->srcCanvas);
if ($this->useErrorImg !== 'byPhp') {
$this->afterCanvasToCanvas($this->canvas, $this->getExtension(), $this->bgId);
}
} else {
$this->addError('setSrcCanvasToCanvas');
}
}
##### ERSTELLT DIE BOOSTING-STRUKTUR
private function createBoosting(): void
{
if (!$this->doBoost) return;
$allExists = true;
foreach ($this->getBoostFactorList() as $factor) {
if (!file_exists($this->getBoostLocation($factor))) {
$allExists = false;
break;
}
}
if ($allExists) return;
$canvasTarget = null;
foreach ($this->getBoostFactorList() as $factor) {
$w = max(1, (int) round($this->getSrcoWidth() / $factor));
$h = max(1, (int) round($this->getSrcoHeight() / $factor));
if ($factor == 2) {
switch ($this->getSrcoExtension()) {
case 'gif':  $src = imagecreatefromgif($this->getSrcoLocation()); break;
case 'jpg':  $src = imagecreatefromjpeg($this->getSrcoLocation()); break;
case 'png':  $src = imagecreatefrompng($this->getSrcoLocation()); break;
case 'webp': $src = imagecreatefromwebp($this->getSrcoLocation()); break;
}
} else {
$src = $canvasTarget;
}
$target = imagecreatetruecolor($w, $h);
if ($target) {
$bgId = $this->afterCreateCanvas($target, $this->getBoostExtension());
if (@imagecopyresampled($target, $src, 0, 0, 0, 0, $w, $h, $w * 2, $h * 2)) {
$this->afterCanvasToCanvas($target, $this->getBoostExtension(), $bgId);
switch ($this->getBoostExtension()) {
case 'gif':  $qual = false; break;
case 'jpg':  $qual = self::calcDynamicQualityBySize(82, $w * $h); break;
case 'png':  $qual = 7; break;
case 'webp': $qual = self::calcDynamicQualityBySize(82, $w * $h); break;
}
if (!$this->imageXXX($target, $this->getBoostExtension(), $qual, $this->getBoostLocation($factor), false)) {
$this->addError('boostingSave', $factor . 'x');
break;
}
} else {
$this->addError('boostingCopy', $factor . 'x');
break;
}
} else {
$this->addError('boostingCreate', $factor . 'x');
}
$canvasTarget = $target;
}
if (isset($src)) imagedestroy($src);
if (isset($canvasTarget)) imagedestroy($canvasTarget);
}
##### NACHBEARBEITUNG NACH CANVAS-KOPIE
private function afterCanvasToCanvas(&$canvas, string $ext, int $bgId): void
{
if ($ext == 'gif') {
imagecolortransparent($canvas, $bgId);
} elseif ($ext == 'png') {
imagesavealpha($canvas, true);
}
}
##### SPEICHERT ODER GIBT DAS BILD ALS STRING ZURÜCK
private function imageXXX($canvas, $ext, $qual, $saveLoc = false, $getContent = false)
{
$nullOrTarget = $saveLoc ?: null;
ob_start();
$success = false;
switch ($ext) {
case 'gif':  $success = imagegif($canvas, $nullOrTarget); break;
case 'jpg':  $success = imagejpeg($canvas, $nullOrTarget, $qual); break;
case 'png':  $success = imagepng($canvas, $nullOrTarget, $qual); break;
case 'webp': $success = imagewebp($canvas, $nullOrTarget, $qual); break;
}
$content = ob_get_clean();
if (!$success) return false;
if ($getContent) {
if ($saveLoc && file_exists($saveLoc)) {
return file_get_contents($saveLoc);
}
return $content;
}
return true;
}
########################### STATISCHE HILFSMETHODEN
##### BERECHNET DYNAMISCHE QUALITÄT BASIEREND AUF BILDGRÖSSE
public static function calcDynamicQualityBySize(int $ql, int $pixelCount): int
{
$ql     = (int) $ql;
$mp     = $pixelCount / 1000000;
$rangeQ = 26;
$maxQ   = $ql + ($rangeQ - ($rangeQ / 100 * $ql));
$minQ   = $maxQ - $rangeQ;
if ($mp < 0.0025) return min(100, $maxQ);
if ($mp > 40) return max(10, $minQ);
$percent = ($mp - 0.0025) / (40 - 0.0025) * 100;
$q = $maxQ - ($rangeQ / 100 * $percent);
return (int) round(max(10, min(100, $q)));
}
##### BERECHNET OPTIMALES ZUSCHNEIDEN
public static function calcOptimalClipping(array $input): array
{
$defaults = ['w' => 0, 'h' => 0, 'targetRatio' => 1, 'targetScale' => 1];
$input    = array_merge($defaults, $input);
$srcW     = $input['w'];
$srcH     = $input['h'];
$tarRatio = $input['targetRatio'];
$srcRatio = $srcW / $srcH;
if ($srcRatio > $tarRatio) {
$h = $srcH;
$w = $srcH * $tarRatio;
} else {
$w = $srcW;
$h = $srcW / $tarRatio;
}
$scl = $input['targetScale'];
$w   = self::calcScaledValue(['scale' => $scl, 'size' => $w]);
$h   = self::calcScaledValue(['scale' => $scl, 'size' => $h]);
$mx  = $srcW - $w;
$my  = $srcH - $h;
$ml  = $mx / 2;
$mt  = $my / 2;
return [
'marginX'      => $mx,
'marginLeft'   => $ml,
'marginRight'  => $mx - $ml,
'marginY'      => $my,
'marginTop'    => $mt,
'marginBottom' => $my - $mt,
'calculatedWidth'      => $w,
'calculatedHeight'     => $h,
'calculatedScale'      => $scl,
'calculatedPixelCount' => $w * $h,
];
}
##### BERECHNET BREITE/HÖHE AUS MODUS, GRÖSSE UND RATIO
private static function calcHWbyMSR(array $input): array
{
$defaults = ['modus' => 'l', 'size' => 0, 'ratio' => 1];
$input    = array_merge($defaults, $input);
$modus    = $input['modus'];
$size     = $input['size'];
$ratio    = $input['ratio'];
if ($modus == 'p') {
$w = sqrt($size * $ratio);
$h = $w / $ratio;
} elseif ($modus == 'w') {
$w = $size;
$h = $w / $ratio;
} elseif ($modus == 'h') {
$h = $size;
$w = $h * $ratio;
} elseif ($modus == 'l') {
if ($ratio > 1) {
$w = $size;
$h = $w / $ratio;
} else {
$h = $size;
$w = $h * $ratio;
}
} else {
if ($ratio > 1) {
$h = $size;
$w = $h * $ratio;
} else {
$w = $size;
$h = $w / $ratio;
}
}
return ['calculatedWidth' => $w, 'calculatedHeight' => $h];
}
##### PARST EINEN RATIO-STRING
private static function numberSplitter($input)
{
if (is_numeric($input)) {
return ['x' => $input, 'y' => 1, 'x:y' => $input . ':1', 'ratio' => $input];
}
if (is_string($input) && preg_match('/^(\d+)[:\/](\d+)$/', $input, $m)) {
$x = (int) $m[1];
$y = (int) $m[2];
return ['x' => $x, 'y' => $y, 'x:y' => "$x:$y", 'ratio' => $x / $y];
}
return null;
}
##### BERECHNET SKALIERTEN WERT
private static function calcScaledValue(array $input): int
{
$scale = $input['scale'] ?? 1;
$size = $input['size'] ?? 0;
return max($scale, (int) round($size / $scale) * $scale);
}
##### BERECHNET DIMENSION AUS PIXELANZAHL UND RATIO
private static function calcDimension(array $input): array
{
$defaults = ['targetPixelCount' => 0, 'targetRatio' => 1];
$input    = array_merge($defaults, $input);
$pixels   = $input['targetPixelCount'];
$ratio    = $input['targetRatio'];
$w        = sqrt($pixels * $ratio);
$h        = $w / $ratio;
return [
'calculatedWidth'  => $w,
'calculatedHeight' => $h,
'calculatedRatio'  => $w / $h,
'calculatedPixelCount' => $w * $h,
];
}
##### EXTRAHIERT PFAD-INFORMATIONEN
private static function pathInfo(string $loc): array
{
$loc       = (string) $loc;
$d         = $b = $e = $f = $slashPos = false;
$lastBack  = strrpos($loc, '\\');
$lastSlash = strrpos($loc, '/');
if ($lastBack !== false && $lastSlash !== false) {
$slashPos = max($lastBack, $lastSlash);
} elseif ($lastBack !== false) {
$slashPos = $lastBack;
} elseif ($lastSlash !== false) {
$slashPos = $lastSlash;
}
if ($slashPos !== false) {
$d = substr($loc, 0, $slashPos);
$b = substr($loc, $slashPos + 1);
$f = $b;
$dot = strrpos($b, '.');
if ($dot !== false) {
$e = substr($b, $dot + 1);
$f = substr($b, 0, $dot);
} elseif (str_starts_with($b, '.')) {
$e = substr($b, 1);
$f = '';
}
} else {
$dot = strrpos($loc, '.');
if ($dot !== false) {
$b = $loc;
$e = substr($loc, $dot + 1);
$f = substr($loc, 0, $dot);
} elseif (str_starts_with($loc, '.')) {
$b = $loc;
$e = substr($loc, 1);
$f = '';
}
}
return ['dirname' => $d, 'basename' => $b, 'extension' => $e, 'filename' => $f];
}
##### PRÜFT PHP-VERSION
private static function isMinPhp(string $compareVersion): bool
{
return version_compare(PHP_VERSION, $compareVersion) >= 0;
}
}
?>
<?php
/**
* FV_ActionHandler – Verarbeitet POST-Aktionen (Login, Upload, Delete)
* =================================================================
*
* Diese Klasse kümmert sich um alle serverseitigen Aktionen, die über
* POST-Requests angestoßen werden. Sie enthält keine Ausgabe-Logik,
* sondern füllt nur das $site_message-Array mit Erfolgs- oder Fehlermeldungen.
*
*
* UPLOAD-BENACHRICHTIGUNGEN MIT THUMBNAILS
* ----------------------------------------
* Bei erfolgreichen Uploads werden Benachrichtigungen generiert:
*   - 1 Datei:     Thumbnail (falls Bild) + "Die Datei 'name' wurde erfolgreich hochgeladen"
*   - 2-3 Dateien: Jede Datei mit Thumbnail/Icon + Dateiname, dann Sammeltext
*   - 4+ Dateien:  Thumbnails der ersten 3 Bilder + "+X" + Sammeltext
*
*
* DELETE-BENACHRICHTIGUNGEN
* -------------------------
*   - Thumbnails werden VOR dem Löschen generiert (für Bild-Dateien)
*   - Einzelne Datei:    Thumbnail/Icon + "Die Datei 'name' wurde gelöscht"
*   - Einzelner Ordner:  "📁 Der Ordner 'name' wurde gelöscht"
*   - Mehrere Dateien:   Thumbnails (max. 3) + "+X" + Sammeltext
*   - Nur Ordner:        Sammeltext ohne Thumbnails
*
*
* THUMBNAIL-GENERIERUNG
* ---------------------
*   - Größe: 20x20 Pixel
*   - Format: WebP (Base64 Data-URL über FV_Image::get('inline'))
*   - Nur für Bild-Dateien (jpg, jpeg, png, gif, webp)
*   - Bei Nicht-Bildern oder Fehlern: Fallback-Icon (📄)
*
* =====================================================================
*/
class FV_ActionHandler
{
########################### KONSTANTEN
##### POST-FELDNAMEN
public const FIELD_LOGIN_SUBMIT  = 'loginSubmit';
public const FIELD_UPLOAD_SUBMIT = 'uploadSubmit';
public const FIELD_ACTION        = 'action';
public const FIELD_PASSWORD      = 'password';
public const FIELD_DIR           = 'dir';
public const FIELD_DATA          = 'data[]';
public const FIELD_CSRF          = 'csrfToken';
public const FIELD_FILE          = 'file';
##### AKTIONSWERTE
public const ACTION_DELETE   = 'delete';
public const ACTION_DOWNLOAD = 'download';
public const ACTION_SHARE    = 'share';
##### FORMULAR-IDS (OPTIONAL)
public const FORM_UPLOAD = 'upload-form';
public const FORM_LOGIN  = 'login-form';
private array $site;
private array $messages;
private bool $debug = false;
private const THUMB_SIZE = 20;
private const MAX_THUMBS_IN_MESSAGE = 3;
private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
private FV_FileSystem $CL_FileSystem;
########################### KONSTRUKTOR
##### KONSTRUKTOR: ÜBERNIMMT SITE- UND MESSAGES-REFERENZEN SOWIE FILESYSTEM
public function __construct(array &$site, array &$messages, ?FV_FileSystem $fileSystem = null)
{
$this->site          = &$site;
$this->messages      = &$messages;
$this->debug         = FV_Core::isDebugEnabled(strtolower(__CLASS__));
$this->CL_FileSystem = $fileSystem ?? new FV_FileSystem();
}
##### INTERNER DEBUG-AUSGABE
private function debug(string $message): void
{
if ($this->debug) {
error_log("[FV_ActionHandler] " . $message);
}
}
########################### ÖFFENTLICHE HANDLER
##### VERARBEITET LOGIN-VERSUCH
public function handleLogin(string $password, bool $skipCsrf = false): void
{
//-- CSRF-Token validieren
if (!$skipCsrf && !FV_Helper::validateCsrfToken()) {
$this->messages['error'][] = FV_Lang::get('csrfError');
$this->debug("Login: CSRF-Fehler");
return;
}
if (FV_Core::$enableDemo) {
$this->messages['error'][] = FV_Lang::get('loginDemoNotPossible');
$this->debug("Login: Demo-Modus, nicht möglich");
return;
}
$password = trim($password);
$stored   = FV_Core::$adminPassword;
$loginOk  = false;
$isHash = (strlen($stored) >= 60 && (
strpos($stored, '$2y$') === 0 ||
strpos($stored, '$2a$') === 0 ||
strpos($stored, '$2b$') === 0 ||
strpos($stored, '$argon2i$') === 0 ||
strpos($stored, '$argon2id$') === 0
));
if ($isHash) {
$loginOk = password_verify($password, $stored);
} else {
$loginOk = (strlen($stored) >= 60)
? (hash('sha256', $password) === $stored)
: ($password === $stored);
}
if ($loginOk) {
session_regenerate_id(true);
$_SESSION['admin']['key'] = hash('sha384', FV_Core::insDir() . FV_Core::$adminPassword);
$_SESSION['admin']['timestamp'] = time();
$_SESSION['csrfToken'] = bin2hex(random_bytes(32));
$this->debug("Login erfolgreich, neues CSRF-Token generiert");
header('location: ' . FV_Core::pathList('currentUrl'));
die();
} else {
$this->messages['error'][] = FV_Lang::get('wrongPassword');
unset($_SESSION['admin']);
$this->debug("Login fehlgeschlagen");
}
}
##### VERARBEITET UPLOAD-VORGANG
public function handleUpload(array $files, string $targetDirName = '', bool $skipCsrf = false): void
{
if (!$skipCsrf && !FV_Helper::validateCsrfToken()) {
$this->messages['error'][] = FV_Lang::get('csrfError');
$this->debug("Upload: CSRF-Fehler");
return;
}
$this->debug("Upload gestartet, CSRF ok");
if ($this->site['isLoggedAsAdmin'] === null) {
$this->messages['error'][] = FV_Lang::get('uploadDemoNotPossible');
$this->debug("Upload: Demo-Modus");
return;
}
if ($this->site['isLoggedAsAdmin'] === false) {
$this->messages['error'][] = FV_Lang::get('uploadLoginRequired');
$this->debug("Upload: Nicht eingeloggt");
return;
}
if (!FV_Core::$enableUpload) {
$this->messages['error'][] = FV_Lang::get('uploadsDisabled');
$this->debug("Upload: Uploads deaktiviert");
return;
}
if (!$this->isValidFilesArray($files)) {
$this->messages['error'][] = FV_Lang::get('noFileOrUnsupported');
$this->debug("Upload: Keine gültigen Dateien");
return;
}
$currentPath = FV_Core::pathList('currentPath');
$targetBaseDir = FV_Core::scanDir() . $currentPath;
//-- Ordner erstellen, falls gewünscht
$targetBaseDir = $this->createTargetFolder($targetDirName, $currentPath, $targetBaseDir);
if ($targetBaseDir === false) {
return;
}
//-- Schreibrechte prüfen
if (!is_writable($targetBaseDir)) {
$this->messages['error'][] = FV_Lang::get('uploadWritePermission');
$this->debug("Upload: Keine Schreibrechte in " . $targetBaseDir);
return;
}
//-- Dateien verarbeiten
$this->processUploadedFiles($files, $targetBaseDir);
if (!$skipCsrf && empty($this->messages['error'])) {
$_SESSION['csrfToken'] = bin2hex(random_bytes(32));
$this->debug("Upload erfolgreich, neues CSRF-Token generiert");
}
}
##### VERARBEITET LÖSCH-AKTIONEN
public function handleDelete(array $data, bool $skipCsrf = false): void
{
if (!$skipCsrf && !FV_Helper::validateCsrfToken()) {
$this->messages['error'][] = FV_Lang::get('csrfError');
$this->debug("Delete: CSRF-Fehler");
return;
}
$this->debug("Delete gestartet, CSRF ok, Data: " . print_r($data, true));
if ($this->site['isLoggedAsAdmin'] === null) {
$this->messages['error'][] = FV_Lang::get('deleteDemoNotPossible');
$this->debug("Delete: Demo-Modus");
return;
}
if ($this->site['isLoggedAsAdmin'] === false) {
$this->messages['error'][] = FV_Lang::get('deleteLoginRequired');
$this->debug("Delete: Nicht eingeloggt");
return;
}
if (!FV_Core::$enableDelete) {
$this->messages['error'][] = FV_Lang::get('deleteDisabled');
$this->debug("Delete: Löschen deaktiviert");
return;
}
if (empty($data) || !is_array($data)) {
$this->messages['info'][] = FV_Lang::get('noElementsSelected');
$this->debug("Delete: Keine Elemente ausgewählt");
return;
}
if (!FV_FileSystem::canWrite()) {
$this->messages['error'][] = FV_Lang::get('cannotDeleteWrite');
$this->debug("Delete: Keine Schreibrechte");
return;
}
$deletedFiles = [];
$deletedFolders = [];
$errorCount = 0;
foreach ($data as $elemName) {
$cleanElemName = FV_FileSystem::normalizeRelativePath((string)$elemName);
if ($cleanElemName === false) {
$this->messages['error'][] = FV_Lang::get('invalidPathWithName', htmlspecialchars($elemName));
$errorCount++;
continue;
}
$elemLocation = FV_Helper::slashBeautifier(FV_Core::scanDir() . $cleanElemName);
$elemName = basename($elemLocation);
$thumbHtml = '';
if (is_file($elemLocation) && $this->isImageFile($elemName)) {
$thumbHtml = $this->generateThumbnailHtml($elemLocation);
}
$result = $this->deleteSingleElement($elemLocation, $elemName);
if ($result['success']) {
if ($result['type'] === 'file') {
$deletedFiles[] = [
'name' => $result['name'],
'thumb' => $thumbHtml
];
} elseif ($result['type'] === 'folder') {
$deletedFolders[] = $result['name'];
}
} else {
$errorCount++;
}
}
$this->addDeleteSuccessMessages($deletedFiles, $deletedFolders);
if ($errorCount > 0) {
$this->messages['info'][] = FV_Lang::get('deleteErrors', $errorCount);
}
$this->debug("Delete abgeschlossen - Dateien: " . count($deletedFiles) . ", Ordner: " . count($deletedFolders) . ", Fehler: $errorCount");
if (!$skipCsrf && (count($deletedFiles) > 0 || count($deletedFolders) > 0)) {
$_SESSION['csrfToken'] = bin2hex(random_bytes(32));
$this->debug("Delete erfolgreich, neues CSRF-Token generiert");
}
}
##### GIBT DIE AKTUELLEN MELDUNGEN ZURÜCK
public function getMessages(): array
{
return $this->messages;
}
########################### UPLOAD-HELFER
##### PRÜFT, OB DAS $_FILES-ARRAY GÜLTIGE EINTRÄGE ENTHÄLT
private function isValidFilesArray(array $files): bool
{
if (!isset($files['name']) || !is_array($files['name'])) {
return false;
}
foreach ($files['name'] as $name) {
if (!empty($name)) {
return true;
}
}
return false;
}
##### ERSTELLT ZIEL-ORDNER, FALLS GEWÜNSCHT
private function createTargetFolder(string $targetDirName, string $currentPath, string $defaultBaseDir)
{
if (empty($targetDirName)) {
return $defaultBaseDir;
}
$targetDirName = trim($targetDirName);
$newDirPath = FV_FileSystem::secureWritablePath($currentPath . $targetDirName);
if ($newDirPath === false) {
$this->messages['error'][] = FV_Lang::get('invalidFolderName');
return false;
}
if (is_dir($newDirPath)) {
$this->messages['error'][] = FV_Lang::get('folderExists', '<i>' . htmlspecialchars($targetDirName) . '</i>');
return $newDirPath . '/';
}
if ($this->CL_FileSystem->createDirInstance($newDirPath)) {
$this->messages['success'][] = FV_Lang::get('folderCreated', '<i>' . htmlspecialchars($targetDirName) . '</i>');
return $newDirPath . '/';
}
$this->messages['error'][] = FV_Lang::get('folderCreateFailed', '<i>' . htmlspecialchars($targetDirName) . '</i>');
return false;
}
##### VERARBEITET DIE EIGENTLICHEN UPLOAD-DATEIEN
private function processUploadedFiles(array $files, string $targetBaseDir): void
{
$uploadedCount      = 0;
$failedCount        = 0;
$uploadedNames      = [];
$uploadedPaths      = [];
$uploadedImagePaths = [];
foreach ($files['name'] as $key => $val) {
if (empty($val)) {
continue;
}
$fileName = $files['name'][$key] ?? '';
$tempName = $files['tmp_name'][$key] ?? '';
$error = $files['error'][$key] ?? UPLOAD_ERR_NO_FILE;
if ($error !== UPLOAD_ERR_OK) {
$this->addUploadErrorMessage($fileName, $error);
$failedCount++;
continue;
}
if (empty($fileName) || empty($tempName)) {
$this->messages['error'][] = FV_Lang::get('noFileOrUnsupported');
$failedCount++;
continue;
}
$targetFilePath = $targetBaseDir . $fileName;
$saveResult = $this->saveUploadedFile($fileName, $tempName, $targetBaseDir, $targetFilePath);
if ($saveResult['success']) {
$uploadedCount++;
$uploadedNames[] = $fileName;
$uploadedPaths[] = $targetFilePath;
if ($this->isImageFile($fileName)) {
$uploadedImagePaths[] = $targetFilePath;
}
} else {
$failedCount++;
}
}
$this->debug("Upload Zusammenfassung - Erfolgreich: $uploadedCount, Fehlgeschlagen: $failedCount");
if ($uploadedCount > 0) {
$this->addUploadSuccessMessages($uploadedCount, $uploadedNames, $uploadedPaths, $uploadedImagePaths);
}
}
##### SPEICHERT EINE EINZELNE UPLOAD-DATEI
private function saveUploadedFile(string $fileName, string $tempName, string $targetBaseDir, string $targetFilePath): array
{
$arrName             = FV_Core::pathInfo($fileName);
$targetFileName      = $arrName['basename'];
$targetFileExtension = strtolower($arrName['extension']);
if (!str_starts_with($targetFilePath, FV_Core::scanDir())) {
$this->messages['error'][] = FV_Lang::get('invalidTargetPath');
return ['success' => false, 'reason' => 'invalid_target'];
}
if (file_exists($targetFilePath)) {
$this->messages['error'][] = FV_Lang::get('fileExists', '<i>' . htmlspecialchars($targetFileName) . '</i>');
return ['success' => false, 'reason' => 'exists'];
}
if ($fileName === '.htaccess') {
$this->messages['error'][] = FV_Lang::get('fileTypeNotAllowed', $targetFileExtension);
return ['success' => false, 'reason' => 'blocked'];
}
if (FV_Core::$enableMimeValidation && function_exists('finfo_open')) {
if (!$this->validateMimeType($tempName, $targetFileExtension, $targetFileName)) {
return ['success' => false, 'reason' => 'mime_mismatch'];
}
}
if (move_uploaded_file($tempName, $targetFilePath)) {
$this->debug("Upload erfolgreich: " . $targetFileName);
return ['success' => true, 'path' => $targetFilePath];
} else {
$this->messages['error'][] = FV_Lang::get('uploadFailed', '<i>' . htmlspecialchars($targetFileName) . '</i>');
$this->debug("Upload fehlgeschlagen: " . $targetFileName);
return ['success' => false, 'reason' => 'move_failed'];
}
}
##### VALIDIERT MIME-TYP EINER UPLOAD-DATEI (WENN AKTIVIERT)
private function validateMimeType(string $tempName, string $extension, string $fileName): bool
{
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$detectedMime = finfo_file($finfo, $tempName);
finfo_close($finfo);
$blocked = array_map('trim', explode(',', FV_Core::$mimeTypeMappingBlock));
if (in_array($extension, $blocked)) {
$this->messages['error'][] = FV_Lang::get('fileTypeNotAllowed', $extension);
return false;
}
$mappingString = FV_Core::$defaultMimeMapping;
if (!empty(FV_Core::$mimeTypeMapping)) {
$mappingString .= ', ' . FV_Core::$mimeTypeMapping;
}
$mimeMap = [];
$parts = array_map('trim', explode(',', $mappingString));
foreach ($parts as $part) {
if ($part === '') continue;
$pair = array_map('trim', explode(':', $part));
if (count($pair) === 2) {
$mimeMap[strtolower($pair[0])] = $pair[1];
}
}
if (isset($mimeMap[$extension])) {
$expectedMime = $mimeMap[$extension];
if ($detectedMime !== $expectedMime) {
$this->messages['error'][] = FV_Lang::get('fileMimeMismatch', '<i>' . htmlspecialchars($fileName) . '</i>', $detectedMime);
return false;
}
}
return true;
}
##### FÜGT EINE UPLOAD-FEHLERMELDUNG HINZU
private function addUploadErrorMessage(string $fileName, int $error): void
{
$errorMsg = FV_Lang::getUploadErrorMessage($error);
$this->messages['error'][] = FV_Lang::get('uploadFailed', '<i>' . htmlspecialchars($fileName) . '</i>') . ': ' . $errorMsg;
$this->debug("Upload-Fehler: $fileName - Code: $error");
}
########################### UPLOAD-BENACHRICHTIGUNGEN (MIT THUMBNAILS)
##### FÜGT ERFOLGSMELDUNGEN JE NACH ANZAHL DER UPLOADS HINZU
private function addUploadSuccessMessages(int $count, array $names, array $paths, array $imagePaths): void
{
if ($count === 1) {
$this->addSingleUploadSuccess($names[0], $paths[0], !empty($imagePaths));
} elseif ($count <= 3) {
$this->addMultipleUploadSuccessDetailed($names, $paths, $imagePaths);
} else {
$this->addMultipleUploadSuccessSummary($count, $imagePaths);
}
}
##### EINZELNER UPLOAD (MIT THUMBNAIL)
private function addSingleUploadSuccess(string $fileName, string $filePath, bool $isImage): void
{
$display = $isImage ? '🖼️' : '📄';
if ($isImage) {
$thumb = $this->generateThumbnailHtml($filePath);
if (!empty($thumb)) {
$display = $thumb;
}
}
$this->messages['success'][] = $display . ' ' . FV_Lang::get('uploadSuccess', '<i>' . htmlspecialchars($fileName) . '</i>');
}
##### MEHRERE UPLOADS (2-3) – DETAILIERTE AUFLISTUNG
private function addMultipleUploadSuccessDetailed(array $names, array $paths, array $imagePaths): void
{
$items = [];
$imagePathsByIndex = [];
foreach ($imagePaths as $imgPath) {
$imagePathsByIndex[array_search($imgPath, $paths)] = $imgPath;
}
for ($i = 0; $i < count($names); $i++) {
$isImage = isset($imagePathsByIndex[$i]);
$display = $isImage ? '🖼️' : '📄';
if ($isImage) {
$thumb = $this->generateThumbnailHtml($imagePathsByIndex[$i]);
if (!empty($thumb)) {
$display = $thumb;
}
}
$items[] = $display . ' ' . htmlspecialchars($names[$i]);
}
$this->messages['success'][] = implode('  ', $items) . ' ' . FV_Lang::get('uploadSuccessMultiple', count($names));
}
##### MEHRERE UPLOADS (4+) – ZUSAMMENFASSUNG MIT MAX. 3 THUMBNAILS
private function addMultipleUploadSuccessSummary(int $count, array $imagePaths): void
{
$thumbsHtml = '';
$imageCount = count($imagePaths);
$displayThumbs = min($imageCount, self::MAX_THUMBS_IN_MESSAGE);
for ($i = 0; $i < $displayThumbs; $i++) {
$thumbsHtml .= $this->generateThumbnailHtml($imagePaths[$i]);
}
if ($imageCount > self::MAX_THUMBS_IN_MESSAGE) {
$thumbsHtml .= '<span class="notify-thumb-more">+' . ($imageCount - self::MAX_THUMBS_IN_MESSAGE) . '</span>';
}
$this->messages['success'][] = $thumbsHtml . ' ' . FV_Lang::get('uploadSuccessMultiple', $count);
}
########################### THUMBNAIL-HELFER
##### PRÜFT, OB EINE DATEI EIN BILD IST
private function isImageFile(string $fileName): bool
{
$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
return in_array($ext, self::IMAGE_EXTENSIONS);
}
##### GENERIERT EINEN HTML-THUMBNAIL-STRING (DATA-URL)
private function generateThumbnailHtml(string $imagePath): string
{
if (!file_exists($imagePath)) {
return '';
}
try {
$img = new FV_Image();
$img->imageFile($imagePath)
->modus('q')
->size(self::THUMB_SIZE)
->quality(85)
->extension('webp');
$dataUrl = $img->get('inline');
return '<img src="' . $dataUrl . '" class="notify-thumb" />';
} catch (Exception $e) {
$this->debug("Thumbnail-Generierung fehlgeschlagen: " . $e->getMessage());
return '';
}
}
########################### DELETE-HELFER
##### LÖSCHT EIN EINZELNES ELEMENT (DATEI ODER ORDNER)
private function deleteSingleElement(string $elemLocation, string $elemName): array
{
$this->debug("Delete versuche: " . $elemLocation);
$this->debug("Existiert: " . (file_exists($elemLocation) ? 'JA' : 'NEIN'));
$this->debug("Ist Datei: " . (is_file($elemLocation) ? 'JA' : 'NEIN'));
$this->debug("Ist Ordner: " . (is_dir($elemLocation) ? 'JA' : 'NEIN'));
if (str_starts_with($elemLocation, FV_Core::cacheDir())) {
$this->debug("Löscht Cache-Element");
$this->CL_FileSystem->eraseInstance($elemLocation);
$this->messages['success'][] = FV_Lang::get('cacheElemDeleted');
return ['success' => true, 'type' => 'cache', 'name' => $elemName, 'location' => $elemLocation];
}
if (is_dir($elemLocation)) {
$this->debug("Löscht Ordner");
$this->CL_FileSystem->clearCacheElemsByDirInstance($elemLocation);
$this->CL_FileSystem->eraseInstance($elemLocation);
return ['success' => true, 'type' => 'folder', 'name' => $elemName, 'location' => $elemLocation];
}
if (file_exists($elemLocation) && is_file($elemLocation)) {
$this->debug("Versuche unlink: " . $elemLocation);
if ($this->CL_FileSystem->deleteFileInstance($elemLocation, true)) {
$this->CL_FileSystem->clearCacheElemByLocationInstance($elemLocation);
$this->debug("Delete erfolgreich: " . $elemLocation);
return ['success' => true, 'type' => 'file', 'name' => $elemName, 'location' => $elemLocation];
} else {
$errorMsg = error_get_last()['message'] ?? 'unknown error';
$this->debug("Delete fehlgeschlagen: " . $elemLocation . " - " . $errorMsg);
$this->messages['error'][] = FV_Lang::get('fileDeleteFailed', '<i>' . htmlspecialchars($elemName) . '</i>');
return ['success' => false, 'type' => 'file', 'name' => $elemName];
}
} else {
$this->debug("Datei nicht gefunden: " . $elemLocation);
$this->messages['error'][] = FV_Lang::get('fileNotFound', '<i>' . htmlspecialchars($elemName) . '</i>');
return ['success' => false, 'type' => null, 'name' => $elemName];
}
}
########################### DELETE-BENACHRICHTIGUNGEN
##### FÜGT ERFOLGSMELDUNGEN FÜR GELÖSCHTE ELEMENTE HINZU
private function addDeleteSuccessMessages(array $deletedFiles, array $deletedFolders): void
{
$fileCount = count($deletedFiles);
$folderCount = count($deletedFolders);
if ($fileCount === 0 && $folderCount === 0) {
return;
}
if ($fileCount === 0 && $folderCount === 1) {
$this->messages['success'][] = '📁 ' . FV_Lang::get('folderDeleted', '<i>' . htmlspecialchars($deletedFolders[0]) . '</i>');
return;
}
if ($folderCount === 0 && $fileCount === 1) {
$this->addSingleFileDeleteSuccess($deletedFiles[0]);
return;
}
$this->addMultipleDeleteSuccess($deletedFiles, $deletedFolders);
}
##### EINZELNE DATEI GELÖSCHT (MIT THUMBNAIL)
private function addSingleFileDeleteSuccess(array $file): void
{
$display = empty($file['thumb']) ? '📄' : $file['thumb'];
$this->messages['success'][] = $display . ' ' . FV_Lang::get('fileDeleted', '<i>' . htmlspecialchars($file['name']) . '</i>');
}
##### MEHRERE ELEMENTE GELÖSCHT (ZUSAMMENFASSUNG)
private function addMultipleDeleteSuccess(array $deletedFiles, array $deletedFolders): void
{
$fileCount = count($deletedFiles);
$folderCount = count($deletedFolders);
$thumbsHtml = '';
$displayThumbs = min($fileCount, self::MAX_THUMBS_IN_MESSAGE);
for ($i = 0; $i < $displayThumbs; $i++) {
if (!empty($deletedFiles[$i]['thumb'])) {
$thumbsHtml .= $deletedFiles[$i]['thumb'];
}
}
if ($fileCount > self::MAX_THUMBS_IN_MESSAGE) {
$thumbsHtml .= '<span class="notify-thumb-more">+' . ($fileCount - self::MAX_THUMBS_IN_MESSAGE) . '</span>';
}
if ($fileCount > 0 && $folderCount > 0) {
$this->messages['success'][] = $thumbsHtml . ' ' . FV_Lang::get('deleteSuccessMultiple', $fileCount + $folderCount);
} elseif ($fileCount > 0) {
$this->messages['success'][] = $thumbsHtml . ' ' . FV_Lang::get('deleteSuccessMultipleFiles', $fileCount);
} elseif ($folderCount > 0) {
$this->messages['success'][] = FV_Lang::get('deleteSuccessMultipleFolders', $folderCount);
}
}
}
?>
<?php
/**
* FV_AssetHandler – Verwaltung statischer Assets (JS, CSS, Icons, Fonts, Logos)
* =========================================================================
*
* Diese Klasse kümmert sich um die Auslieferung aller statischen Assets
* des FileViewers. Sie wird für Requests mit ?file=... aufgerufen und
* gibt die entsprechenden Ressourcen zurück.
*
*
* UNTERSTÜTZTE ASSETS
* -------------------
*   - ic:        Übersichtsseite aller Icons
*   - lang:      JavaScript-Übersetzungsobjekt
*   - js:        Zusammengefasste JavaScript-Dateien
*   - css:       Zusammengefasste CSS-Dateien
*   - iconFont:  Font-Datei (WOFF2)
*   - logo:      Logo als PNG
*   - logoSmall: Kleines Logo/Favicon als PNG
*
*
* VERWENDUNG (in template.php)
* -----------------------------
*   if (!empty($_GET['file'])) {
*       FV_AssetHandler::handle($_GET['file'], $_GET);
*   }
*
* =====================================================================
*/
class FV_AssetHandler
{
########################### KONSTANTEN FÜR ASSET-TYPEN
public const ASSET_IC         = 'ic';
public const ASSET_LANG       = 'lang';
public const ASSET_JS         = 'js';
public const ASSET_CSS        = 'css';
public const ASSET_ICON_FONT  = 'iconfont';
public const ASSET_LOGO       = 'logo';
public const ASSET_LOGO_SMALL = 'logosmall';
public const PARAM_LANG       = 'lang';
public static function handle(string $file, array $get = []): void
{
$file = strtolower(trim($file));
switch ($file) {
case self::ASSET_IC:
self::renderIconOverview();
break;
case self::ASSET_LANG:
$language = $get[self::PARAM_LANG] ?? 'de';
self::renderLanguageFile($language);
break;
case self::ASSET_JS:
self::renderJavaScript();
break;
case self::ASSET_CSS:
self::renderCss();
break;
case self::ASSET_ICON_FONT:
self::renderIconFont();
break;
case self::ASSET_LOGO:
self::renderLogo();
break;
case self::ASSET_LOGO_SMALL:
self::renderLogoSmall();
break;
default:
//-- Unbekanntes Asset -> 404
http_response_code(404);
echo 'Asset not found';
break;
}
}
########################### INTERNE RENDER-METHODEN
##### GIBT DIE ICON-ÜBERSICHTSSEITE AUS (LISTE ALLER VERFÜGBAREN ICONS)
private static function renderIconOverview(): void
{
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<meta name="robots" content="noindex,nofollow,noarchive" />
<meta name="viewport" content="width=device-width, maximum-scale=1, initial-scale=1" />
<link rel="stylesheet" href="<?= htmlentities(FV_Core::insPath(true) . '?file=' . self::ASSET_CSS) ?>" type="text/css" />
<style>
* { padding: 0; }
body { padding: 20px; }
li {
font-size: 10px;
float: left;
width: 160px;
padding: 5px 5px 5px 40px;
list-style-type: none;
position: relative;
color: rgba(255, 255, 255, .4);
}
li::before {
position: absolute;
left: 12px;
top: 6px;
font-size: 170%;
color: rgba(255, 255, 255, .8);
}
</style>
</head>
<body>
<ul>
<?php
foreach (FV_UI::icArr() as $k => $v) {
echo '<li data-ic="' . $k . '">' . $k . '</li>';
}
?>
</ul>
</body>
</html>
<?php
exit;
}
##### GIBT DIE JAVASCRIPT-ÜBERSETZUNGSDATEI AUS
private static function renderLanguageFile(string $language): void
{
header('content-type: application/javascript; charset=utf-8');
FV_Lang::language($language);
echo FV_Lang::convertListToJs(FV_Lang::getAll());
exit;
}
##### GIBT DIE ZUSAMMENGEFASSTE JAVASCRIPT-DATEI AUS
private static function renderJavaScript(): void
{
header('content-type: application/javascript; charset=utf-8');
?>
'use strict';
function qs(selector, context = document) {
if (!selector) return null;
if (selector.startsWith('#')) {
const id = selector.substring(1);
return document.getElementById(id);
}
try {
return context.querySelector(selector);
} catch (e) {
return null;
}
}
function qsa(selector, context = document) {
if (!selector) return [];
try {
return Array.from(context.querySelectorAll(selector));
} catch (e) {
return [];
}
}
function check(element, bool, trigger) {
if (!element || !element.matches) return;
if (!element.matches('[type="radio"], [type="checkbox"]')) return;
let parent = element.closest('li') || element.closest('label');
if (element.matches('[type="radio"]')) {
const name = element.getAttribute('name');
const form = element.closest('form');
if (form) {
qsa(`[name="${name}"]`, form).forEach(brother => {
const brotherParent = brother.closest('li') || brother.closest('label');
if (brother.checked) {
brotherParent?.classList.add('marked');
} else {
brotherParent?.classList.remove('marked');
}
});
}
}
if (bool === true) {
element.checked = true;
parent?.classList.add('marked');
} else {
element.checked = false;
parent?.classList.remove('marked');
}
if (trigger === true) {
element.dispatchEvent(new Event('change', { bubbles: true }));
}
}
if (typeof smartMessageContainer === 'undefined') {
var smartMessageContainer = null;
}
function setNotifyContainer(container) {
smartMessageContainer = container;
}
function notify(text, type, delay) {
if (delay === false) delay = 0;
setTimeout(() => {
if (!smartMessageContainer) return;
let cssClass;
if (type === 'success') cssClass = 'success_message';
else if (type === 'error') cssClass = 'error_message';
else cssClass = 'info_message';
const li = document.createElement('li');
li.className     = cssClass;
li.innerHTML     = text;
li.style.display = 'none';
smartMessageContainer.appendChild(li);
setTimeout(() => {
li.style.display = 'block';
li.style.transition = 'all 200ms ease';
li.style.opacity = '1';
}, 10);
setTimeout(() => {
li.style.opacity = '0';
setTimeout(() => li.remove(), 200);
}, 4000);
}, delay);
}
function scrollToElem(element, options = {}) {
const settings = {
delay    : 0,
duration : 400,
topOffset: 110,
ifNeeded : true,
...options
};
settings.topOffset = parseInt(settings.topOffset) || 68;
settings.duration = parseInt(settings.duration) || 400;
const rect      = element.getBoundingClientRect();
const elemPos   = rect.top + window.scrollY;
let   needed    = true;
const scrollPos = window.scrollY;
let   targetPos = elemPos - settings.topOffset;
if (targetPos < 0) targetPos = 0;
if (settings.ifNeeded) {
const elemHeight = rect.height;
const windowHeight = window.innerHeight;
if (
(elemHeight + elemPos) > (scrollPos + windowHeight - 20) ||
((scrollPos + settings.topOffset) > targetPos)
) {
needed = true;
} else {
needed = false;
}
}
if (needed) {
if (targetPos === scrollPos) {
settings.duration = 0;
}
setTimeout(() => {
window.scrollTo({
top: targetPos,
behavior: settings.duration > 0 ? 'smooth' : 'auto'
});
}, settings.delay);
}
}
window.qs                 = qs;
window.qsa                = qsa;
window.check              = check;
window.notify             = notify;
window.setNotifyContainer = setNotifyContainer;
window.scrollToElem       = scrollToElem;
'use strict';
if (typeof window.fvPopoverManager === 'undefined') {
var fvPopoverManager = {
backdrop: null,
init: function () {
this.backdrop = document.getElementById('popover-backdrop');
if (!this.backdrop) return;
this.initBackdrop();
this.initPopovers();
return this;
},
initBackdrop: function () {
const closePopover = (e) => {
e.preventDefault();
e.stopPropagation();
const openPopover = document.querySelector('[popover]:popover-open');
if (openPopover) {
openPopover.hidePopover();
}
return false;
};
this.backdrop.addEventListener('click', closePopover);
this.backdrop.addEventListener('touchstart', closePopover, { passive: false });
this.backdrop.addEventListener('touchmove', (e) => {
e.preventDefault();
e.stopPropagation();
}, { passive: false });
this.backdrop.addEventListener('touchend', (e) => {
e.preventDefault();
e.stopPropagation();
}, { passive: false });
},
initPopovers: function () {
const popovers = document.querySelectorAll('[popover]');
popovers.forEach(popover => {
popover.addEventListener('beforetoggle', (event) => {
if (event.newState === 'open') {
this.backdrop.classList.add('active');
document.body.style.overflow = 'hidden';
document.body.style.position = 'fixed';
document.body.style.width = '100%';
} else {
this.backdrop.classList.remove('active');
document.body.style.overflow = '';
document.body.style.position = '';
document.body.style.width = '';
}
});
});
}
};
} else {
var fvPopoverManager = window.fvPopoverManager;
}
window.fvPopoverManager = fvPopoverManager;
'use strict';
if (typeof window.fvExplorerManager === 'undefined') {
var fvExplorerManager = {
elements: {
body       : null,
exploreList: null,
checkList  : [],
shareList  : [],
mainSubmit : null,
actionList : []
},
UI_STATE: {
MARK    : 'mark-on',
DOWNLOAD: 'download-on',
SHARE   : 'share-on'
},
init: function () {
if (typeof window.qs !== 'function') {
console.error('core.js not loaded before explorer.js');
return this;
}
this.elements.body        = document.body;
this.elements.exploreList = window.qs('ul.elist');
this.elements.checkList   = window.qsa('input[type="checkbox"]', this.elements.exploreList);
this.elements.shareList   = window.qsa('.action-link[href^="#"]', this.elements.exploreList);
this.elements.mainSubmit  = window.qs('#main-submit');
this.elements.actionList  = window.qsa('input[name="action"], select[name="action"], button[name="action"]', window.qs('#controler_out'));
this.initCheckboxes();
this.initShareLinks();
this.initActions();
return this;
},
initCheckboxes: function () {
if (this.elements.checkList.length === 0) return;
this.elements.checkList.forEach(chk => {
chk.checked ? window.check(chk, true, false) : window.check(chk, false, false);
});
this.elements.checkList.forEach(chk => {
chk.addEventListener('change', () => {
window.check(chk, chk.checked, false);
this.setMarkedElemText();
});
});
this.setMarkedElemText();
},
setMarkedElemText: function () {
if (!this.elements.mainSubmit) return;
const i = this.elements.checkList.filter(chk => chk.checked).length;
if (i === 1) {
this.elements.mainSubmit.innerHTML = i + ' <small>' + (window.fvLang?.item || 'Item') + '</small>';
} else {
this.elements.mainSubmit.innerHTML = i + ' <small>' + (window.fvLang?.items || 'Items') + '</small>';
}
},
initShareLinks: function () {
if (this.elements.shareList.length === 0) return;
this.elements.shareList.forEach(shareLink => {
shareLink.addEventListener('click', (e) => {
e.preventDefault();
const baseUrl = window.location.href.split('#')[0];
let   hash    = shareLink.getAttribute('href');
hash    = hash.replace(/#+/g, '#');
if (!hash.startsWith('#')) hash = '#' + hash;
const url = baseUrl + hash;
navigator.clipboard.writeText(url).then(
() => window.notify(window.fvLang?.urlCopied || 'Url wurde in die Zwischenablage kopiert', 'info', false),
() => window.notify(window.fvLang?.copyNotSupported || 'Kopieren wird von deinem Browser nicht unterstützt', 'error', false)
);
return false;
});
});
const hash = window.location.hash;
if (hash.length > 2) {
const target = document.getElementById(hash.substr(1));
if (target && this.elements.exploreList?.contains(target)) {
target.click();
}
}
},
initActions: function () {
this.elements.actionList.forEach(action => {
action.addEventListener('change', () => this.initAction(action));
});
this.elements.actionList.forEach(action => {
action.checked ? window.check(action, true, true) : window.check(action, false, false);
});
},
initAction: function (element) {
const action = element.value;
const isOn = element.checked ? true : false;
this.elements.body.classList.remove(this.UI_STATE.MARK, this.UI_STATE.DOWNLOAD, this.UI_STATE.SHARE);
this.elements.actionList.forEach(actionItem => window.check(actionItem, false, false));
if (isOn) window.check(element, true, false);
if (action === 'delete' && isOn) {
this.elements.body.classList.add(this.UI_STATE.MARK);
const i = this.elements.checkList.filter(chk => chk.checked).length;
if (i === 0) {
window.notify(window.fvLang?.deleteSelectElements || 'Zu löschende Elemente auswählen..', 'info', false);
} else {
window.notify(window.fvLang?.deleteMarked?.replace('{1}', i) || i + ' zu löschende Elemente markiert', 'info', false);
}
this.setMarkedElemText();
} else if (action === 'download' && isOn) {
this.elements.body.classList.add(this.UI_STATE.DOWNLOAD);
const dd = window.qsa('a.action-link', this.elements.exploreList).find(a => a.hasAttribute('download'));
if (dd) {
window.notify(window.fvLang?.clickOn$ToDownload?.replace('{1}', '<span data-ic="download"></span>') || 'Klicke zum Download', 'info', false);
} else {
window.notify(window.fvLang?.noDownloadableFiles || 'Keine Dateien zum Download', 'info', false);
}
} else if (action === 'share' && isOn) {
this.elements.body.classList.add(this.UI_STATE.SHARE);
const dd = window.qsa('a.action-link', this.elements.exploreList).find(a => a.getAttribute('href')?.startsWith('#'));
if (dd) {
window.notify(window.fvLang?.clickOn$ToShare?.replace('{1}', '<span data-ic="share-square-o"></span>') || 'Klicke zum Teilen', 'info', false);
} else {
window.notify(window.fvLang?.noShareableFiles || 'Keine Dateien zum Teilen', 'info', false);
}
}
}
};
} else {
var fvExplorerManager = window.fvExplorerManager;
}
window.fvExplorerManager = fvExplorerManager;
'use strict';
if (typeof window.fvSortForm === 'undefined') {
var fvSortForm = {
form: null,
init: function () {
if (typeof window.qs !== 'function') {
console.error('core.js not loaded before sort.js');
return this;
}
this.form = window.qs('#sort-form');
if (!this.form) return this;
this.form.querySelectorAll('select').forEach(select => {
select.addEventListener('change', () => this.submit());
});
return this;
},
submit: function () {
if (this.form) {
this.form.submit();
}
}
};
} else {
var fvSortForm = window.fvSortForm;
}
window.fvSortForm = fvSortForm;
if (typeof window.elayer === 'undefined') {
window.elayer = function (options) {
/*
EINBAUCODE JS:
window.elayer({
closeText: 'schließen',
prevText: 'zurück',
nextText: 'vor',
selector: '.itm .main-link'
});
EINBAUCODE HTML:
<a href="bild.jpg" class="main-link">Bild</a>
*/
const STATE = {
CLOSED      : 'closed',
LOADING     : 'loading',
SHOW        : 'show',
ZOOM_LOADING: 'loading',
ZOOM_IN     : 'in',
ZOOM_OUT    : 'out'
};
const defaultSettings = {
imageMimes  : 'webp,png,jpg,jpeg,gif,bmp,ogg,xvid,avi',
videoMimes  : 'mp4,xvid,avi',
audioMimes  : 'mp3,wav,ogg',
excludeMimes: 'ini,woff,woff2,otf,ttf,zip,7z,rar,iso,lnk',
prevText    : 'prev',
nextText    : 'next',
zoomText    : 'Zum Vergrößern doppelklicken',
closeText   : 'close',
zoomFactor  : 2.5,
selector    : 'a.elayer, .elayer a',
};
const settings = Object.assign({}, defaultSettings, options);
const existingElayer = document.querySelector('#elayer');
if (existingElayer) {
existingElayer.remove();
}
document.body.setAttribute('data-elayer', STATE.CLOSED);
const elayerDiv = document.createElement('div');
elayerDiv.id = 'elayer';
elayerDiv.innerHTML = `
<div id="elayer_control-container">
<div id="elayer_control">
<a href="#prev">${settings.prevText}</a>
<a href="#next">${settings.nextText}</a>
<a href="#close">${settings.closeText}</a>
</div>
<div id="elayer_progress" style="width: 70%;height: 70%;"></div>
</div>
<div id="elayer_main">
<div id="elayer_content_close"></div>
<div id="elayer_content">
<img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" alt="elayer image" />
</div>
</div>
`;
document.body.appendChild(elayerDiv);
document.body.classList.add('elayer-desc-on');
const elements = {
body        : document.body,
self        : elayerDiv,
prev        : elayerDiv.querySelector('a[href="#prev"]'),
next        : elayerDiv.querySelector('a[href="#next"]'),
info        : elayerDiv.querySelector('a[href="#info"]'),
download    : elayerDiv.querySelector('a[href="#download"]'),
close       : elayerDiv.querySelector('a[href="#close"]'),
progress    : elayerDiv.querySelector('#elayer_progress'),
main        : elayerDiv.querySelector('#elayer_main'),
content     : elayerDiv.querySelector('#elayer_content'),
contentClose: elayerDiv.querySelector('#elayer_content_close'),
acItem      : null,
prevItem    : null,
nextItem    : null,
targetItem  : null,
timerDT     : false
};
const elayerFunc = {
savePlayed: function () {
/*
EINBAUCODE JS:
elayerFunc.savePlayed();
EINBAUCODE HTML:
<video src="video.mp4"></video>
*/
if (elements.targetItem && (elements.targetItem.tagName === 'VIDEO' || elements.targetItem.tagName === 'AUDIO') && elements.acItem) {
let time = elements.targetItem.currentTime;
if (time < 4) time = 0;
elements.acItem.setAttribute('data-elayer-played', parseFloat(time).toFixed(1));
}
},
readPlayed: function () {
/*
EINBAUCODE JS:
elayerFunc.readPlayed();
EINBAUCODE HTML:
<video src="video.mp4" data-elayer-played="10.5"></video>
*/
if (elements.targetItem && (elements.targetItem.tagName === 'VIDEO' || elements.targetItem.tagName === 'AUDIO') &&
elements.acItem && elements.acItem.hasAttribute('data-elayer-played')) {
elements.targetItem.currentTime = parseFloat(elements.acItem.getAttribute('data-elayer-played')) - 2;
}
},
close: function () {
/*
EINBAUCODE JS:
elayerFunc.close();
EINBAUCODE HTML:
<a href="#close">schließen</a>
*/
this.savePlayed();
elements.body.setAttribute('data-elayer', STATE.CLOSED);
elements.body.removeAttribute('data-elayer-zoom');
elements.body.removeAttribute('data-elayer-media-type');
const cacheItm = elements.acItem;
elements.acItem = false;
setTimeout(() => {
if (cacheItm && cacheItm.nodeType === 1) {
cacheItm.setAttribute('data-elayer', '');
}
}, 400);
if (elements.targetItem) {
elements.targetItem.remove();
elements.targetItem = false;
}
},
zoomIn: function () {
/*
EINBAUCODE JS:
elayerFunc.zoomIn();
EINBAUCODE HTML:
<div id="elayer_content">...</div>
*/
const contentStyles = window.getComputedStyle(elements.content);
const boxW = parseInt(elements.content.clientWidth) -
(parseInt(contentStyles.paddingLeft) + parseInt(contentStyles.paddingRight));
const boxH = parseInt(elements.content.clientHeight) -
(parseInt(contentStyles.paddingTop) + parseInt(contentStyles.paddingBottom));
const elemW = parseInt(elements.targetItem.offsetWidth) * settings.zoomFactor;
const elemH = parseInt(elements.targetItem.offsetHeight) * settings.zoomFactor;
elements.body.setAttribute('data-elayer-zoom', STATE.ZOOM_LOADING);
elements.targetItem.style.height = elemH + 'px';
elements.targetItem.style.width = elemW + 'px';
elements.targetItem.style.maxHeight = 'none';
elements.targetItem.style.maxWidth = 'none';
setTimeout(() => {
elements.content.scrollLeft = (elemW - boxW) / 2;
elements.content.scrollTop = (elemH - boxH) / 2;
elements.body.setAttribute('data-elayer-zoom', STATE.ZOOM_IN);
}, 5);
setTimeout(() => {
elements.content.scrollLeft = (elemW - boxW) / 2;
elements.content.scrollTop = (elemH - boxH) / 2;
}, 20);
},
zoomOut: function () {
/*
EINBAUCODE JS:
elayerFunc.zoomOut();
EINBAUCODE HTML:
<div id="elayer_content">...</div>
*/
elements.body.setAttribute('data-elayer-zoom', STATE.ZOOM_LOADING);
elements.targetItem.removeAttribute('style');
setTimeout(() => {
elements.body.setAttribute('data-elayer-zoom', STATE.ZOOM_OUT);
}, 5);
},
showZoomInfo: function () {
/*
EINBAUCODE JS:
elayerFunc.showZoomInfo();
EINBAUCODE HTML:
<div id="elayer_main"></div>
*/
if (typeof localStorage !== 'undefined') {
const now = Date.now();
const lastTime = localStorage.getItem('elayer_zoominfotime');
if (!lastTime || now - lastTime > 5 * 60 * 1000) {
const infoDiv = document.createElement('div');
infoDiv.id = 'elayer_zoom-info';
infoDiv.textContent = settings.zoomText;
elements.main.prepend(infoDiv);
setTimeout(() => infoDiv.remove(), 3000);
localStorage.setItem('elayer_zoominfotime', now);
}
}
},
generateSelector: function (separated) {
/*
EINBAUCODE JS:
var selector = elayerFunc.generateSelector('jpg,png,gif');
EINBAUCODE HTML:
<a href="bild.jpg"></a>
*/
if (!separated) return false;
const prefix = '[href*=".';
const suffix = '"]';
const lower = separated.toLowerCase().split(',');
const upper = separated.toUpperCase().split(',');
if (lower.length > 0) {
return prefix + lower.join(suffix + ',' + prefix) + suffix + ',' +
prefix + upper.join(suffix + ',' + prefix) + suffix;
}
return false;
},
openItem: function (itm) {
/*
EINBAUCODE JS:
elayerFunc.openItem($(this));
EINBAUCODE HTML:
<a href="bild.jpg" class="main-link">Bild</a>
*/
this.savePlayed();
if (elements.targetItem) {
elements.targetItem.remove();
elements.targetItem = false;
}
elements.acItem = itm;
elements.acItem.setAttribute('data-elayer', STATE.LOADING);
elements.body.setAttribute('data-elayer', STATE.LOADING);
elements.body.removeAttribute('data-elayer-zoom');
let mediaType = 'data';
const imageSelector = this.generateSelector(settings.imageMimes);
if (imageSelector && elements.acItem.matches(imageSelector)) {
mediaType = 'image';
} else {
const audioSelector = this.generateSelector(settings.audioMimes);
if (audioSelector && elements.acItem.matches(audioSelector)) {
mediaType = 'audio';
} else {
const videoSelector = this.generateSelector(settings.videoMimes);
if (videoSelector && elements.acItem.matches(videoSelector)) {
mediaType = 'video';
}
}
}
elements.body.setAttribute('data-elayer-media-type', mediaType);
const clickedMediaUrl = elements.acItem.getAttribute('href');
const t_start = Date.now();
elements.prevItem = false;
elements.nextItem = false;
let stopPrev = false;
let startNext = false;
let myI = 0;
let itms = [];
if (elements.acItem.hasAttribute('data-elayer-group')) {
const groupName = elements.acItem.getAttribute('data-elayer-group');
itms = Array.from(document.querySelectorAll(`a[data-elayer-group="${groupName}"]`));
} else if (elements.acItem.closest('[data-elayer-group=""]')) {
const container = elements.acItem.closest('[data-elayer-group=""]');
itms = Array.from(container.querySelectorAll('a[data-elayer]'));
}
if (itms.length > 1) {
for (let index = 0; index < itms.length; index++) {
const el = itms[index];
el.setAttribute('data-elayer', '');
if (elements.acItem.isSameNode(el)) {
stopPrev = true;
startNext = true;
myI = index;
} else if (!stopPrev && !elements.acItem.isSameNode(el)) {
elements.prevItem = el;
} else if (startNext && !elements.acItem.isSameNode(el)) {
elements.nextItem = el;
startNext = false;
}
}
const perc = (myI + 1) / itms.length * 100;
elements.progress.style.display = '';
elements.progress.style.width = perc + '%';
elements.progress.style.height = perc + '%';
} else {
elements.progress.style.display = 'none';
}
if (!elements.prevItem) {
elements.prev.setAttribute('data-elayer-status', 'inactive');
} else {
elements.prev.setAttribute('data-elayer-status', 'loading');
}
if (!elements.nextItem) {
elements.next.setAttribute('data-elayer-status', 'inactive');
} else {
elements.next.setAttribute('data-elayer-status', 'loading');
}
setTimeout(() => {
if (elements.prevItem) {
elements.prev.setAttribute('data-elayer-status', '');
}
if (elements.nextItem) {
elements.next.setAttribute('data-elayer-status', '');
}
}, 100);
if (mediaType === 'audio') {
elements.content.innerHTML = `<audio src="${clickedMediaUrl}" controls autoplay></audio>`;
} else if (mediaType === 'video') {
elements.content.innerHTML = `<video src="${clickedMediaUrl}" controls autoplay></video>`;
} else if (mediaType === 'image') {
elements.content.innerHTML = `<img src="${clickedMediaUrl}">`;
this.showZoomInfo();
} else {
const isYt = clickedMediaUrl.includes('youtube.com/watch?v=');
if (isYt) {
elements.content.innerHTML = `<iframe width="560px" height="315px" src="${clickedMediaUrl.replace('youtube.com/watch?v=', 'youtube.com/embed/')}" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>`;
} else {
elements.content.innerHTML = `<iframe src="${clickedMediaUrl}"></iframe>`;
}
}
const minimumWait = 250;
elements.targetItem = elements.content.firstElementChild;
if (mediaType === 'video' || mediaType === 'audio') {
this.fadeInItem(minimumWait);
elements.targetItem.addEventListener('ended', function () {
this.currentTime = 0;
});
} else if (mediaType === 'image') {
elements.targetItem.addEventListener('load', () => {
const t_end = Date.now();
const t_duration = parseInt(t_end - t_start);
const time = t_duration < minimumWait ? minimumWait - t_duration : 20;
this.fadeInItem(time);
});
} else {
this.fadeInItem(minimumWait);
}
return false;
},
fadeInItem: function (time) {
/*
EINBAUCODE JS:
elayerFunc.fadeInItem(250);
EINBAUCODE HTML:
<div id="elayer_content">...</div>
*/
setTimeout(() => {
this.readPlayed();
elements.body.setAttribute('data-elayer', STATE.SHOW);
elements.acItem.setAttribute('data-elayer', STATE.SHOW);
const li = elements.acItem.closest('li');
if (li) {
const aDown = li.querySelector('a[download]');
if (aDown && elements.download) {
elements.download.setAttribute('href', aDown.getAttribute('href'));
elements.download.setAttribute('download', aDown.getAttribute('download'));
elements.download.setAttribute('data-elayer-status', '');
}
}
}, time);
},
addElem: function (el) {
/*
EINBAUCODE JS:
elayerFunc.addElem($('a.main-link'));
EINBAUCODE HTML:
<a href="bild.jpg" class="main-link">Bild</a>
*/
if (el.tagName === 'A' && el.hasAttribute('href') && !el.hasAttribute('download')) {
el.setAttribute('data-elayer', '');
if (el._elayerClickHandler) {
el.removeEventListener('click', el._elayerClickHandler);
}
el._elayerClickHandler = (e) => {
e.preventDefault();
this.openItem(el);
return false;
};
el.addEventListener('click', el._elayerClickHandler);
}
}
};
elements.content.addEventListener('click', (e) => {
/*
EINBAUCODE JS:
elements.content.addEventListener('click', handler);
EINBAUCODE HTML:
<div id="elayer_content">...</div>
*/
if (!elements.body.hasAttribute('data-elayer-zoom') ||
elements.body.getAttribute('data-elayer-zoom') !== STATE.ZOOM_LOADING) {
const now = Date.now();
const timesince = now - (elements.timerDT || 0);
let dt = false;
if ((timesince < 440) && (timesince > 4)) {
dt = true;
}
elements.timerDT = now;
if (dt && elements.body.getAttribute('data-elayer-zoom') === STATE.ZOOM_IN) {
elayerFunc.zoomOut();
elements.timerDT = false;
} else if (dt) {
elayerFunc.zoomIn();
elements.timerDT = false;
}
}
e.preventDefault();
return false;
});
elements.prev.addEventListener('click', (e) => {
/*
EINBAUCODE JS:
elements.prev.addEventListener('click', handler);
EINBAUCODE HTML:
<a href="#prev">prev</a>
*/
if (elements.prevItem && elements.prev.getAttribute('data-elayer-status') === '') {
elements.prevItem.click();
}
e.preventDefault();
return false;
});
elements.next.addEventListener('click', (e) => {
/*
EINBAUCODE JS:
elements.next.addEventListener('click', handler);
EINBAUCODE HTML:
<a href="#next">next</a>
*/
if (elements.nextItem && elements.next.getAttribute('data-elayer-status') === '') {
elements.nextItem.click();
}
e.preventDefault();
return false;
});
elements.close.addEventListener('click', (e) => {
/*
EINBAUCODE JS:
elements.close.addEventListener('click', handler);
EINBAUCODE HTML:
<a href="#close">close</a>
*/
elayerFunc.close();
e.preventDefault();
return false;
});
elements.contentClose.addEventListener('click', (e) => {
/*
EINBAUCODE JS:
elements.contentClose.addEventListener('click', handler);
EINBAUCODE HTML:
<div id="elayer_content_close"></div>
*/
elayerFunc.close();
e.preventDefault();
return false;
});
document.addEventListener('keydown', (event) => {
/*
EINBAUCODE JS:
document.addEventListener('keydown', handler);
EINBAUCODE HTML:
<body>...</body>
*/
if (elements.body.getAttribute('data-elayer') === STATE.SHOW ||
elements.body.getAttribute('data-elayer') === STATE.LOADING) {
if (event.keyCode === 37 || event.keyCode === 8) {
elements.prev.click();
event.preventDefault();
return false;
} else if (event.keyCode === 39 || event.keyCode === 32) {
elements.next.click();
event.preventDefault();
return false;
} else if (event.keyCode === 27) {
elements.close.click();
event.preventDefault();
return false;
}
}
});
const selectorExcl = elayerFunc.generateSelector(settings.excludeMimes);
const items = document.querySelectorAll(settings.selector);
items.forEach(el => {
if (!selectorExcl || !el.matches(selectorExcl)) {
if (el.tagName === 'A' && el.hasAttribute('href') && !el.hasAttribute('download')) {
elayerFunc.addElem(el);
}
}
});
};
}
'use strict';
document.addEventListener('DOMContentLoaded', () => {
if (typeof window.qs !== 'function') {
return;
}
const smartMessage = window.qs('ul#smart-message');
if (smartMessage && typeof window.setNotifyContainer === 'function') {
window.setNotifyContainer(smartMessage);
}
if (typeof window.fvPopoverManager !== 'undefined' && window.fvPopoverManager.init) {
window.fvPopoverManager.init();
}
if (typeof window.fvExplorerManager !== 'undefined' && window.fvExplorerManager.init) {
window.fvExplorerManager.init();
}
if (typeof window.fvSortForm !== 'undefined' && window.fvSortForm.init) {
window.fvSortForm.init();
}
if (typeof window.elayer === 'function') {
window.elayer({
closeText: window.fvLang?.close || 'close',
prevText : window.fvLang?.prev || 'prev',
nextText : window.fvLang?.next || 'next',
selector : '.itm .main-link',
});
}
let   timerBackclick = false;
const $controlerOut  = window.qs('#controler_out');
const $loginForm     = window.qs('#login-form');
const $uploadForm    = window.qs('#upload-form');
document.addEventListener('click', (e) => {
if (
!$controlerOut?.contains(e.target) &&
!$loginForm?.contains(e.target) &&
!$uploadForm?.contains(e.target)
) {
clearTimeout(timerBackclick);
timerBackclick = setTimeout(() => {
if (document.body.getAttribute('data-elayer') === 'show') {
if (window.fvExplorerManager && window.fvExplorerManager.elements) {
window.fvExplorerManager.elements.actionList
.filter(a => a.checked)
.forEach(action => window.check(action, false, true));
}
}
}, 30);
}
});
initPageLoadMonitor();
});
function initPageLoadMonitor() {
const body                   = document.body;
let   timeoutId              = null;
let   isNavigating           = false;
let   isUpload               = false;
let   uploadProgressInterval = null;
function startPulsingBar() {
isUpload = false;
isNavigating = true;
body.setAttribute('data-loading-state', 'loading');
body.setAttribute('data-loading-progress', '100');
body.style.setProperty('--loading-progress', '100%');
if (timeoutId) clearTimeout(timeoutId);
timeoutId = setTimeout(() => {
if (isNavigating) {
stopLoadingBar();
}
}, 30000);
}
function startUploadProgress() {
isUpload = true;
isNavigating = true;
body.setAttribute('data-loading-state', 'uploading');
body.setAttribute('data-loading-progress', '0');
body.style.setProperty('--loading-progress', '0%');
if (uploadProgressInterval) clearInterval(uploadProgressInterval);
uploadProgressInterval = setInterval(() => {
if (!isUpload) return;
let current = parseInt(body.getAttribute('data-loading-progress') || '0');
if (current < 90) {
current += Math.random() * 8;
if (current > 90) current = 90;
body.setAttribute('data-loading-progress', Math.floor(current));
body.style.setProperty('--loading-progress', Math.floor(current) + '%');
}
}, 300);
}
function updateUploadProgress(percent) {
if (!isUpload) return;
percent = Math.min(99, Math.max(0, percent));
body.setAttribute('data-loading-progress', percent);
body.style.setProperty('--loading-progress', percent + '%');
}
function stopLoadingBar() {
if (uploadProgressInterval) {
clearInterval(uploadProgressInterval);
uploadProgressInterval = null;
}
if (timeoutId) {
clearTimeout(timeoutId);
timeoutId = null;
}
body.setAttribute('data-loading-state', 'loaded');
body.setAttribute('data-loading-progress', '100');
body.style.setProperty('--loading-progress', '100%');
setTimeout(() => {
isNavigating = false;
isUpload = false;
}, 300);
}
document.addEventListener('submit', (e) => {
const form         = e.target;
const isAjaxForm   = form.hasAttribute('data-ajax') || form.classList.contains('ajax-form');
const hasFileInput = form.querySelector('input[type="file"]') !== null;
const isMultipart  = form.getAttribute('enctype')             === 'multipart/form-data';
if (!isAjaxForm && (hasFileInput || isMultipart)) {
e.preventDefault();
const formData = new FormData(form);
const xhr      = new XMLHttpRequest();
xhr.upload.addEventListener('progress', (event) => {
if (event.lengthComputable) {
const percent = Math.round((event.loaded / event.total) * 100);
updateUploadProgress(percent);
}
});
xhr.upload.addEventListener('loadstart', () => {
startUploadProgress();
});
xhr.addEventListener('load', () => {
if (xhr.status >= 200 && xhr.status < 400) {
const redirect = xhr.getResponseHeader('Location');
if (redirect) {
window.location.href = redirect;
} else {
document.open();
document.write(xhr.responseText);
document.close();
}
}
stopLoadingBar();
});
xhr.addEventListener('error', () => {
stopLoadingBar();
});
xhr.addEventListener('timeout', () => {
stopLoadingBar();
});
xhr.open(form.method || 'POST', form.action || window.location.href);
xhr.send(formData);
return false;
} else if (!isAjaxForm) {
startPulsingBar();
}
});
document.addEventListener('click', (e) => {
let target = e.target;
while (target && target !== document) {
if (target.tagName === 'A') {
const href = target.getAttribute('href');
if (href &&
!href.startsWith('#') &&
!href.startsWith('javascript:') &&
target.target !== '_blank' &&
!target.hasAttribute('data-no-loading')) {
startPulsingBar();
}
break;
}
target = target.parentNode;
}
});
window.addEventListener('popstate', () => {
startPulsingBar();
});
window.addEventListener('load', () => {
if (isNavigating) {
stopLoadingBar();
}
});
window.addEventListener('pageshow', (e) => {
if (e.persisted) {
stopLoadingBar();
}
});
window.addEventListener('pagehide', () => {
if (uploadProgressInterval) {
clearInterval(uploadProgressInterval);
uploadProgressInterval = null;
}
if (timeoutId) {
clearTimeout(timeoutId);
timeoutId = null;
}
});
}
<?php
exit;
}
##### GIBT DIE ZUSAMMENGEFASSTE CSS-DATEI AUS
private static function renderCss(): void
{
header('content-type: text/css; charset=utf-8');
?>
@font-face {
font-family: 'e2icon';
src: url('<?= htmlentities(FV_Core::insPath(true)) ?>?file=<?= self::ASSET_ICON_FONT ?>') format('woff2');
font-weight: normal;
font-style: normal;
font-display: swap;
}
[data-ic]:before {
font-family: 'e2icon';
speak: none;
font-style: normal;
font-weight: normal;
font-display: swap;
-webkit-font-smoothing: antialiased;
text-decoration: none !important;
}
* { border: 0; margin: 0; padding: 0; outline: 0; }
:root { --app-width: 1240px; --app-padding: 20px; --bar-height: 50px; --font-size-base: 14px; --primary-dark-rgb: 19, 87, 107; --primary-accent-rgb: 218, 184, 108; --state-success-rgb: 116, 161, 2; --state-info-rgb: 238, 161, 0; --state-error-rgb: 187, 49, 43; --overlay-bg-rgb: 20, 40, 50; --overlay-close-rgb: 242, 109, 125; --text-primary-rgb: 179, 202, 209; --text-secondary-rgb: 255, 255, 255; --text-light-rgb: 255, 255, 255; --bg-page-rgb: 0, 38, 49; --bg-controler-rgb: 19, 87, 107; --border-light-rgb: 255, 255, 255; --border-accent-rgb: 195, 174, 76; --border-success-rgb: 142, 171, 53; --border-error-rgb: 191, 60, 81; --shadow-small: 1px 1px 3px rgba(0, 0, 0, 0.2); --shadow-medium: 0px 4px 5px -4px rgba(0, 0, 0, 0.5); --shadow-large: 3px 3px 8px rgba(0, 0, 0, 0.4); --shadow-controler: 0px 0px 6px rgba(0, 0, 0, 0.5); --shadow-action: 2px 2px 2px rgba(0, 0, 0, 0.3); --shadow-popover: 0px 18px 25px -10px rgba(0, 0, 0, 0.6); --bg-input: rgb(0, 38, 49); --text-input: rgb(255, 255, 255); --overlay-blur: 3px; --overlay-opacity: 0.3; --transition-fast: all 140ms; --transition-normal: all 200ms; --transition-slow: all 300ms; }
body { background-color: rgb(var(--bg-page-rgb)); color: rgb(var(--text-primary-rgb)); overflow: auto; font-size: var(--font-size-base); line-height: 130%; font-family: "Segoe UI", Segoe, Calibri, Tahoma, Geneva, sans-serif; text-align: left; text-shadow: 0px -1px 0px rgba(0, 0, 0, 0.2); }
:-webkit-autofill { -webkit-transition-delay: 99999s !important; }
::-webkit-input-placeholder { color: rgba(var(--text-secondary-rgb), 0.2); }
::-webkit-scrollbar { width: 8px; height: 8px; }
::-webkit-scrollbar-track { background: rgba(0, 0, 0, 0.2); box-shadow: 0px 0px 3px rgba(0, 0, 0, 0.4) inset; border-radius: 4px; }
::-webkit-scrollbar-thumb { border-radius: 4px; background: rgba(var(--text-secondary-rgb), 0.4); }
@media (max-width: 460px) { body { --app-padding: 12px; }
}
@media (max-width: 300px) { body { --app-padding: 8px; }
}
h1 { font-weight: 100; margin: 0 0 16px 0; color: rgb(var(--text-light-rgb)); }
h1 small { margin-top: 4px; display: block; font-size: 40%; line-height: 120%; opacity: 0.6; }
a { color: rgb(var(--primary-accent-rgb)); text-decoration: none; transition: var(--transition-normal); }
a:hover { color: rgb(var(--text-light-rgb)); }
input, select { margin: 0; padding: 8px 12px; display: inline-block; max-width: 100%; color: var(--text-input); box-sizing: border-box; border-radius: 3px; border: 1px solid rgba(var(--border-light-rgb), 0.2); background: var(--bg-input); line-height: 128%; font-size: 12px; transition: var(--transition-normal); vertical-align: middle; appearance: none; -webkit-appearance: none; }
input[type="submit"] { text-align: center; text-decoration: none; background-color: #71c0d3; color: rgb(var(--text-light-rgb)); border: 1px solid rgba(0, 0, 0, 0.01); font-weight: bold; letter-spacing: 1px; text-shadow: 0px -1px 0px rgba(0, 0, 0, 0.2); min-width: 60px; box-shadow: var(--shadow-medium); }
input[type="submit"][disabled] { opacity: 0.1; pointer-events: none; }
input:-webkit-autofill, input:-webkit-autofill:hover, input:-webkit-autofill:focus, input:-webkit-autofill:active, select:-webkit-autofill, select:-webkit-autofill:hover, select:-webkit-autofill:focus, select:-webkit-autofill:active { -webkit-text-fill-color: var(--text-input) !important; -webkit-box-shadow: 0 0 0 30px var(--bg-input) inset !important; box-shadow: 0 0 0 30px var(--bg-input) inset !important; background-color: var(--bg-input) !important; caret-color: var(--text-input); border: 1px solid rgba(var(--border-light-rgb), 0.2); }
input:-moz-autofill, select:-moz-autofill { filter: none !important; color: var(--text-input) !important; background-color: var(--bg-input) !important; border: 1px solid rgba(var(--border-light-rgb), 0.2); }
input.small { padding: 4px 5px; font-size: 10px; }
select optgroup { font-size: 14px; line-height: 120%; background: #ddd; color: #444; }
select option { padding: 4px 8px; }
#main-submit { position: fixed; display: block; bottom: 8vh; left: calc(50% - 40px); height: 80px; width: 80px; line-height: 24px; opacity: 1; z-index: 40; font-size: 20px; background: rgb(var(--state-error-rgb)); box-shadow: 0px 0px 20px 20px rgb(var(--primary-dark-rgb)); color: rgb(var(--text-light-rgb)); border-radius: 50%; pointer-events: none; transform: scale(0.4) rotateZ(40deg); transition: var(--transition-normal); opacity: 0.0001; }
body.mark-on #main-submit { pointer-events: auto; transform: scale(1) rotateZ(0); opacity: 1; }
#main-submit small { position: absolute; left: 0; bottom: 11px; width: 100%; font-size: 10px; line-height: 120%; }
#page { max-width: var(--app-width); box-sizing: border-box; margin: 0px auto; padding: 60px var(--app-padding) calc(100vh - 200px); position: relative; }
#controler_out { position: fixed; top: 0; left: 0; width: 100%; background: rgba(var(--primary-dark-rgb), 0.92); z-index: 40; box-sizing: border-box; text-align: left; box-shadow: var(--shadow-controler); }
#controler { position: relative; margin: 0px auto; max-width: var(--app-width); height: var(--bar-height); box-sizing: border-box; padding: 0 var(--app-padding); display: flex; flex-direction: row; justify-content: flex-start; align-items: stretch; }
@media (max-width: 460px) { #controler { padding: 0; }
}
#controler>*, #controler>button { margin: 0; border: none; background: transparent; color: rgb(var(--text-light-rgb)); flex: 0; box-shadow: none; align-self: center; cursor: pointer; font-size: 8px; text-transform: uppercase; padding: 0 25px; line-height: var(--bar-height); margin: 0 1px 0 0; text-align: center; position: relative; transition: var(--transition-fast); text-shadow: 0px -1px 0px rgba(0, 0, 0, 0.3); font-family: inherit; }
@media (max-width: 800px) { #controler>*, #controler>button { padding: 0 20px; }
}
@media (max-width: 700px) { #controler>*, #controler>button { padding: 0 10px; }
}
#controler>*.marked, #controler>button.marked { color: rgb(var(--primary-accent-rgb)); }
#controler input { position: absolute; top: -500px; left: 0px; opacity: 0.1; }
#controler>a>img, #controler>a>.ic, #controler>*::before, #controler>button::before { font-size: 24px; padding: 0 8px 0 0; vertical-align: middle; height: 36px; line-height: 120%; }
#controler>a:first-child { padding: 0; }
#controler>a:first-child>img { padding: 0 10px; transform: translateY(1px); }
#controler>a:first-child>.ic { display: inline-block; min-width: 16px; font-size: 30px; padding: 0 20px; }
@media (max-width: 560px) { #controler>*, #controler>button { font-size: 0px; }
#controler>*::before, #controler>button::before { padding: 0px 10px; }
}
@media (max-width: 440px) { #controler>*::before, #controler>button::before { padding: 0px 4px; }
}
#controler>*:hover, #controler>button:hover { background: rgba(var(--text-secondary-rgb), 0.06); }
#controler>#logout-button, #controler>#login-button { justify-content: flex-end; margin-left: auto; }
ul#smart-message { z-index: 38; position: fixed; left: 0; top: 50px; width: 100%; margin: 0; display: block; list-style-type: none; pointer-events: none; }
ul#smart-message>li { display: block; margin: 0px auto; box-sizing: border-box; padding: 4px; text-align: center; border: 0; font-size: 14px; line-height: 120%; font-weight: 400; border-bottom: 1px solid rgb(var(--border-accent-rgb)); text-shadow: 0px 1px 0px rgba(0, 0, 0, 0.8); color: rgb(var(--border-accent-rgb)); background: rgba(0, 0, 0, 0.4); }
.notify-thumb { width: 20px; height: 20px; vertical-align: middle; border-radius: 3px; margin: 0 2px; object-fit: cover; }
.notify-thumb-more { display: inline-block; width: 20px; height: 20px; background: rgba(0, 0, 0, 0.6); border-radius: 3px; text-align: center; line-height: 20px; font-size: 10px; color: white; margin: 0 2px; vertical-align: middle; }
@media (max-width: 600px) { ul#smart-message>li { font-size: 12px; }
}
ul#smart-message>li.success_message { border-color: rgb(var(--border-success-rgb)); color: rgb(var(--border-success-rgb)); }
ul#smart-message>li.error_message { border-color: rgb(var(--border-error-rgb)); color: rgb(var(--border-error-rgb)); }
#login-form input[type="password"] { width: 200px; }
@media (max-width: 430px) { #login-form input[type="password"] { width: 120px; }
}
.overlay-active #content, body[data-elayer="loading"] #content, body[data-elayer="show"] #content, body:has([popover]:popover-open) #content { filter: blur(var(--overlay-blur)); pointer-events: none; opacity: var(--overlay-opacity); transition: var(--transition-normal); }
#content * { scroll-margin: 120px; }
p.path { color: rgba(var(--text-secondary-rgb), 0.5); overflow: auto; padding: 10px 0; white-space: nowrap; margin: 0 0 4px 0; display: block; box-sizing: border-box; width: calc(100% - 170px); }
p.path>* { font-size: 11px; line-height: 11px; display: inline-block; padding: 8px 11px; vertical-align: bottom; }
p.path a { margin: 0px 1px; border-radius: 999px; text-align: center; min-width: 28px; box-sizing: border-box; }
p.path a:hover { background: rgba(0, 0, 0, 0.2); }
p.path b { font-size: 14px; padding-left: 3px; padding-right: 3px; }
@media (max-width: 900px) { p.path a { padding-left: 7px; padding-right: 7px; }
p.path b { padding-left: 1px; padding-right: 1px; }
}
#sort-form { margin: 10px 0 0 0; width: 168px; float: right; }
#sort-form select { font-size: 10px; margin: 0 1px; padding: 4px 8px; width: calc(50% - 4px); border: 1px solid rgba(var(--border-light-rgb), 0.1); float: right; text-align: right; border-radius: 3px; }
@media (max-width: 800px) { p.path { width: calc(100% - 130px); }
#sort-form { width: 126px; }
}
.infobox { margin: 10px 0 0; padding: 10px 0; display: block; position: sticky; bottom: 0; }
.infobox span { color: rgba(var(--text-secondary-rgb), 0.5); font-size: 11px; line-height: 14px; padding: 0px 10px; display: inline-block; }
.infobox span::before { margin: 0 4px 0 0; display: inline-block; }
:root { --bg-elist-item-rgb: 13, 52, 63; --bg-elist-marked-rgb: 153, 128, 70; --bg-elist-thumb-rgb: 0, 0, 0; --bg-elist-thumb-ic-rgb: 13, 52, 63; }
ul.elist { list-style-type: none; text-align: center; display: flex; flex-wrap: wrap; }
ul.elist li { width: calc(20% - 4px); max-width: 100%; height: 64px; margin: 2px; box-sizing: border-box; position: relative; border-radius: 5px; padding: 0; display: block; text-align: left; background: none 50% 40% no-repeat transparent; background-size: cover; transition: opacity 240ms linear, background-position 240ms ease-out; align-items: stretch; box-shadow: var(--shadow-small); perspective: 350px; }
@media (max-width: 1180px) { ul.elist li { width: calc(25% - 4px); }
}
@media (max-width: 900px) { ul.elist li { width: calc((100% / 3) - 4px); }
}
@media (max-width: 700px) { ul.elist li { width: calc(50% - 4px); }
}
@media (max-width: 480px) { ul.elist li { width: calc(100% - 4px); }
}
ul.elist li:hover { background-position: 50% 60%; transition: background-position 3s ease-out; }
ul.elist a.main-link { display: block; height: 100%; overflow: hidden; text-decoration: none; line-height: 120%; color: rgba(var(--text-secondary-rgb), 0.6); padding-left: 60px; box-sizing: border-box; background: rgba(var(--bg-elist-item-rgb), 0.95); transition: background 240ms, opacity 240ms; border-radius: 5px; }
ul.elist li.dir a.main-link { background: rgba(var(--primary-accent-rgb), 0.2); }
body.share-on ul.elist li a.main-link, body.download-on ul.elist li a.main-link, ul.elist li:hover a.main-link { background: rgba(var(--primary-dark-rgb), 0.5); color: rgb(var(--text-light-rgb)); }
ul.elist a.main-link:target, ul.elist a.main-link:focus { background: rgba(var(--primary-dark-rgb), 0.8); color: rgb(var(--text-light-rgb)); }
body.mark-on ul.elist li.marked a.main-link { background-color: rgba(var(--bg-elist-marked-rgb), 0.8); color: rgb(var(--text-light-rgb)); }
body.mark-on ul.elist a.main-link { pointer-events: none; }
ul.elist li a[data-elayer="show"] { background: rgba(var(--text-secondary-rgb), 0.5); }
ul.elist a.action-link { display: block; opacity: 0; position: absolute; height: 48px; width: 48px; top: 8px; left: 8px; overflow: hidden; transform-origin: 0 0; border-radius: 50%; z-index: 20; background: rgb(var(--primary-accent-rgb)); color: rgb(var(--text-light-rgb)); text-align: center; box-shadow: var(--shadow-action); transform-origin: center center; pointer-events: none; transition: var(--transition-normal); }
body.share-on ul.elist a.action-link[href^="#"], body.download-on ul.elist a.action-link[download] { opacity: 1; pointer-events: auto; }
ul.elist a.action-link::before { display: block; font-size: 22px; line-height: 50px; }
ul.elist .thimb { display: block; position: absolute; height: 56px; width: 56px; left: 4px; top: 4px; margin: 0; padding: 4px; z-index: 9; border-radius: 4px; background: rgba(var(--bg-elist-item-rgb), 0.9); font-size: 10px; line-height: 12px; color: rgba(var(--text-secondary-rgb), 0.6); box-sizing: border-box; overflow: hidden; white-space: nowrap; }
body.share-on ul.elist .thimb, body.download-on ul.elist .thimb { display: none; }
ul.elist .thumb { display: block; position: absolute; height: 56px; width: 56px; left: 4px; top: 4px; margin: 0; padding: 0px; z-index: 10; background: transparent no-repeat center center; background-size: cover; overflow: hidden; border-radius: 4px; transition: var(--transition-slow), transform 300ms ease-out, opacity 250ms ease-out 50ms; transform-origin: 0 0; transform-style: preserve-3d; }
ul.elist .thumb[data-ic] { background: rgba(var(--bg-elist-thumb-rgb), 0.3); text-align: center; line-height: 56px; font-size: 20px; color: rgba(var(--text-secondary-rgb), 0.8); }
ul.elist li:hover .thumb { transform: rotateY(75deg); opacity: 0; }
ul.elist li:hover .thumb[data-ic] { transform: rotateY(50deg); opacity: 0.7; }
body.share-on ul.elist li:not(.dir) .thumb, body.download-on ul.elist li:not(.dir) .thumb { transform: rotateY(0deg); opacity: 0.001; transition: var(--transition-normal) !important; }
ul.elist .thumb[data-ic] { font-size: 8px; line-height: 10px; text-transform: uppercase; }
ul.elist li.dir .thumb[data-ic] { color: rgba(var(--text-secondary-rgb), 0.3); }
ul.elist .thumb[data-ic]::before { display: block; margin: 12px 0 0 0; font-size: 28px; line-height: 28px; }
ul.elist .main-link .thumb[data-ic="eye-slash"]::before, ul.elist li.dir .thumb[data-ic]::before { color: rgb(var(--primary-accent-rgb)); }
ul.elist .percent { display: block; position: absolute; right: 7px; bottom: 4px; background: rgba(0, 0, 0, 0.2); height: 2px; width: 45px; border-radius: 1px; }
ul.elist .percent>span { position: absolute; right: 0; display: block; background: rgba(var(--text-secondary-rgb), 0.2); height: 100%; min-width: 1px; border-radius: 1px; }
ul.elist li.dir .percent>span { position: absolute; right: 0; display: block; background: rgba(var(--primary-accent-rgb), 0.7); height: 100%; min-width: 1px; border-radius: 1px; }
ul.elist i { display: block; padding: 6px 6px 4px; font-style: normal; word-break: break-all; }
ul.elist .micro { font-size: 65%; line-height: 120%; }
ul.elist .ribo .micro { display: block; margin: 0 0 1px 0; }
ul.elist .lebo .micro { padding-left: 1px; display: inline-block; }
ul.elist .ribo, ul.elist .lebo { position: absolute; font-size: 80%; opacity: 0.4; line-height: 120%; }
ul.elist .ribo { text-align: right; right: 8px; bottom: 7px; }
ul.elist .lebo { left: 66px; bottom: 4px; }
ul.elist label { position: absolute; left: 0; top: 0; display: none; width: 100%; height: 100%; z-index: 30; border-radius: 2px; cursor: pointer; background: transparent; }
body.mark-on ul.elist label { display: block; }
ul.elist input { position: absolute; right: 10px; bottom: 10px; display: none; }
:root { --nav-size: 50px; --nav-line-size: 1px; }
#elayer { --close-color: rgb(var(--overlay-close-rgb)); position: fixed; left: 0; top: 0; width: 100%; height: 100%; background: rgba(var(--overlay-bg-rgb), 0.9); box-sizing: border-box; display: grid; grid-template-rows: calc(var(--nav-size) + var(--nav-line-size)) auto; font-family: Arial; user-select: none; z-index: 99999; }
body[data-elayer="show"], body[data-elayer="loading"] { overflow: hidden; }
body[data-elayer="closed"] #elayer { display: none; }
#elayer * { margin: 0; padding: 0; border: 0; }
#elayer_control-container { display: block; border: 0px solid rgba(var(--text-secondary-rgb), 0.2); border-bottom-width: var(--nav-line-size); position: relative; }
#elayer_progress { position: absolute; background: rgba(var(--text-secondary-rgb), 0.6); transition: 160ms ease-out 0ms; pointer-events: none; }
#elayer_progress[style*="100%"] { background-color: var(--close-color); }
@media (orientation: landscape) { #elayer_progress { bottom: 0; left: calc(var(--nav-line-size) * -1); width: 1px !important; }
}
@media (orientation: portrait) { #elayer_progress { left: 0; bottom: calc(var(--nav-line-size) * -1); height: 1px !important; }
}
#elayer_control { max-width: 1000px; max-height: 1000px; margin: auto; height: 100%; width: 100%; position: absolute; left: 0; right: 0; top: 0; bottom: 0; display: grid; grid-template-columns: repeat(5, auto); grid-template-areas: 'i d p n c'; grid-auto-flow: dense; align-content: start; justify-content: end; grid-gap: 2px; box-sizing: border-box; }
#elayer_control > a { height: var(--nav-size); width: var(--nav-size); line-height: 100%; color: rgb(var(--text-light-rgb)); box-sizing: border-box; margin: 0; font-size: calc(var(--nav-size) * 0.14); text-align: center; text-decoration: none; position: relative; padding: 0; padding-top: calc(var(--nav-size) - (var(--nav-size) * 0.26)); overflow: hidden; text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3); transition: var(--transition-normal); }
#elayer_control > a[href="#download"] { grid-area: d; }
#elayer_control > a[href="#prev"] { grid-area: p; }
#elayer_control > a[href="#next"] { grid-area: n; }
#elayer_control > a[href="#close"] { grid-area: c; }
#elayer_control > a::before { content: '◯'; line-height: 100%; position: absolute; left: 0; top: calc(var(--nav-size) * 0.1); width: 100%; display: block; font-size: calc(var(--nav-size) * 0.6); }
#elayer_control > a[href="#download"]::before { font-family: 'e2icon'; content: '\f019'; }
#elayer_control > a[href="#prev"]::before { font-family: 'e2icon'; content: '\f104'; }
#elayer_control > a[href="#next"]::before { font-family: 'e2icon'; content: '\f105'; }
#elayer_control > a[href="#close"]::before { content: '✕'; color: var(--close-color); }
#elayer_main::after { content: '✦'; }
#elayer_control > a[data-elayer-status="loading"], #elayer_control > a[data-elayer-status="inactive"] { pointer-events: none; }
#elayer_control > a[data-elayer-status="loading"] { opacity: 0.5; }
#elayer_control > a[data-elayer-status="inactive"] { opacity: 0.1; transform: scale(0.6); }
#elayer_control > a:hover { background: rgba(0, 0, 0, 0.4); }
#elayer_main { overflow: hidden; position: relative; }
#elayer_main::after { pointer-events: none; display: block; width: 100px; height: 100px; line-height: 100px; font-size: 50px; text-align: center; position: absolute; left: 0; top: 0; right: 0; bottom: 0; margin: auto; color: rgb(var(--text-light-rgb)); transition: opacity 200ms; opacity: 0; }
#elayer_content_close { display: block; width: 100%; height: 100%; position: absolute; left: 0; top: 0; z-index: 5; }
body[data-elayer-zoom="in"] #elayer_content_close { width: calc(100% - 16px); height: calc(100% - 16px); }
#elayer_zoom-info { width: 240px; padding: 10px 14px; box-sizing: border-box; background: rgba(0, 0, 0, 0.9); color: rgb(var(--text-light-rgb)); margin-left: -120px; position: absolute; left: 50%; bottom: 10%; z-index: 11; font-size: 13px; text-align: center; border-radius: 8px; pointer-events: none; transition: all 1s; opacity: 1; }
#elayer_content { display: grid; width: 100%; height: 100%; position: relative; padding: 10px; box-sizing: border-box; overflow: auto; }
body[data-elayer-zoom="loading"] #elayer_content, body[data-elayer-zoom="in"] #elayer_content { padding: 0px; }
@keyframes example { 0% { transform: rotate(0deg); }
100% { transform: rotate(360deg); }
}
body[data-elayer="loading"] #elayer_main::after { animation: example 1s linear infinite; opacity: 1; }
#elayer_content > * { max-height: 100%; max-width: 100%; height: auto; width: auto; box-sizing: border-box; align-self: center; justify-self: center; border-radius: 2px; box-shadow: var(--overlay-shadow); transform-origin: center center; transition: var(--transition-fast); position: relative; z-index: 9; }
body[data-elayer-zoom="loading"] #elayer_content > * { transition: all 0ms linear 0ms !important; }
body[data-elayer="loading"] #elayer_content > * { transform: scale(0.94); opacity: 0.005; }
#elayer_content > img { background-image: url('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQAQMAAAAlPW0iAAAABlBMVEUAAAAAAAClZ7nPAAAAAnRSTlMaAMa+N+MAAAAPSURBVAjXY/jPgB3hkAAAfr8P8eDDzn8AAAAASUVORK5CYII='); background-size: 20px auto; overflow: scroll; }
#elayer_content > audio { width: 400px; height: 60px; box-shadow: none; }
#elayer_content > iframe { background: rgba(var(--text-light-rgb), 0.67); max-height: 1200px; max-width: 1200px; height: 100%; width: 100%; box-shadow: none; }
#elayer_content > iframe[src*="youtube.com"] { width: 800px; height: 450px; max-height: 100%; max-width: 100%; }
@media (orientation: landscape) { #elayer { grid-template-rows: auto; grid-template-columns: auto calc(var(--nav-size) + var(--nav-line-size)); grid-auto-flow: dense; }
#elayer_control-container { grid-column-start: 2; grid-column-end: 3; border-width: 0; border-left-width: var(--nav-line-size); }
#elayer_control { grid-template-columns: auto; grid-template-rows: repeat(5, auto); grid-template-areas: 'c' 'n' 'p' 'd' 'i'; }
#elayer_main { grid-column-start: 1; grid-column-end: 2; }
@media (min-width: 1600px) { #elayer { grid-template-columns: auto calc(10vw + var(--nav-line-size)); }
#elayer_control { padding-top: 20px; }
#elayer_control > a { width: 10vw; font-size: calc(var(--nav-size) * 0.2); padding: 12px 5px; line-height: 120%; height: auto; }
#elayer_control > a::before { font-size: calc(var(--nav-size) * 0.2); line-height: 120%; position: relative; top: auto; left: auto; display: inline-block; vertical-align: middle; width: auto; font-size: 20px; margin-right: 10px; }
}
}
[popover] { margin: 0; border: none; padding: 20px 20px 26px; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) scale(0.2); background: rgb(var(--primary-dark-rgb)); width: 400px; box-sizing: border-box; text-align: left; border-radius: 4px; color: rgb(var(--text-light-rgb)); text-shadow: 1px 1px 1px rgba(0, 0, 0, 0.5); opacity: 0; transition: transform 200ms 20ms, opacity 200ms 20ms; }
[popover]:popover-open { opacity: 1; transform: translate(-50%, -50%) scale(1); box-shadow: var(--shadow-popover); }
[popover] input { width: 100% !important; }
[popover] input[type="submit"] { width: 100px !important; float: right; margin: 8px 0 0 0; }
#upload-form input[name="dir"] { margin-top: 8px; width: 160px !important; }
#popover-backdrop { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: transparent; z-index: 30; pointer-events: auto; -webkit-tap-highlight-color: transparent; touch-action: none; }
#popover-backdrop.active { display: block; }
body:has([popover]:popover-open) { overflow: hidden; position: fixed; width: 100%; height: 100%; }
@media (max-width: 480px) { [popover] { width: calc(100% - 30px); left: 15px; right: 15px; transform: translate(0, -50%) scale(0.2); }
[popover]:popover-open { transform: translate(0, -50%) scale(1); }
#upload-form input[name="dir"] { width: 100% !important; }
}
body::before { content: ''; position: fixed; top: 0; left: 0; width: var(--loading-progress, 0%); height: 3px; background: rgb(var(--state-success-rgb)); z-index: 9999; transition: width 0.1s ease-out; box-shadow: 0 0 8px rgba(59, 130, 246, 0.5); pointer-events: none; }
body[data-loading-state="loaded"]::before, body[data-loading-state="loaded"]::after { opacity: 0; transition: opacity 0.2s ease 0.1s; }
body[data-loading-state="loading"]::before { background: rgb(var(--state-success-rgb)); animation: pulse 1s ease-in-out infinite; width: 100% !important; }
body[data-loading-state="loading"]::after { display: none; }
body[data-loading-state="uploading"]::before { background: rgb(var(--state-success-rgb)); transition: width 0.1s ease-out; animation: none; }
body[data-loading-state="uploading"]::after { content: '📤 'attr(data-loading-progress) '%'; background: rgba(16, 185, 129, 0.8); display: block; }
body[data-loading-state="timeout"]::before, body[data-loading-state="error"]::before { background: rgb(var(--state-error-rgb)); animation: none; }
body[data-loading-state="timeout"]::after, body[data-loading-state="error"]::after { display: none; }
body[data-loading-state="loading"], body[data-loading-state="uploading"] { cursor: wait; }
@keyframes pulse { 0%, 100% { opacity: 1; }
50% { opacity: 0.4; }
}
<?php
foreach (FV_UI::icArr() as $k => $v) {
echo '[data-ic="' . $k . '"]::before{content: "\\' . $v . '";}';
}
exit;
}
##### GIBT DIE ICON-FONT-DATEI (WOFF2) AUS
private static function renderIconFont(): void
{
?><?php
header('access-control-allow-origin: *');
header('content-description: web font file');
header('content-type: application/x-font-woff2');
echo base64_decode('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');
die();
?><?php
exit;
}
##### GIBT DAS LOGO ALS PNG AUS
private static function renderLogo(): void
{
?><?php
header('content-type: image/png');
echo base64_decode('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');
die();
?><?php
exit;
}
##### GIBT DAS KLEINE LOGO / FAVICON ALS PNG AUS
private static function renderLogoSmall(): void
{
?><?php
header('content-type: image/png');
echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAMAAAAoLQ9TAAABC1BMVEUAAACKz95zwtRuv9J3xdaN0d/i9PiW1OHG5+50tcOe2OSl2+eR0t+Dy9uP0d95xteBy9p3xdak2eRur7yd1+ODydi24OmAytmm2+ZxwNOd1uOv3uh/vsus3ed/wNCX1eLe8fV4xNbJ5euYzdiAu8Znq7as3uhuvtF3usW84uqGzdyW1OGBy9uw3uir3eeMz93C5eyj1N6IzdzF5+6Z1OGZ1uJ5xtdzwtSDzNug2OSV1OJ9yNnc8fbE5e2k2+W03eSJz92Gzt2Bytpvv9KBvslgpbHZ7vK+4emu3+mn3eia0NuezNSLx9Nmp7Xr+f3N5+214emn09uLzNqTy9eBxtR7v86PwMlsrLlaoa12x06uAAAANnRSTlMAtru7/dO7oVb++Pb18vDo08y/rm5aVDMF9/Tv6ubd1sLCu7u7u7Cwn5yZiHNxaWhdUFA1IRWA/c/mAAAAxUlEQVQY0y3L5baCUBCG4TlHBbu7uzuAvQVFEbv7/q/EId4/s9az5oNqsFSuFBmGyY9Abzvbr76H83ot9g1w7cjxo4rz98Vuwgxf1IN4k0xwEkIejpO02egQh9wkDlhDmkfxpDJggxbLsvXQVYMO6SK4KaVHdbUd4D4JCGa7IIRpGMAPzT+tUJqOvW7QwCMIC1l+ne5euYcQAN90iSQ4sgsPYL6AleOmaM9IO6ZBgud5K4e2HIKeoii8YREDahbsHyvgAPsBIDUeHdOi7DAAAAAASUVORK5CYII=');
die();
?><?php
exit;
}
}
?>
<?php
$site_composition['startTime'] = microtime(true);
// Erst FV initialisieren
FV_Core::construct();
// Debug-Funktion für Template (nach FV_Core::construct, da FV jetzt verfügbar ist)
function template_debug($message) {
if (FV_Core::isDebugEnabled('template')) {
error_log("[template] " . $message);
}
}
template_debug("========== START ==========");
$debugTemplate = FV_Core::isDebugEnabled('template');
########################### FRÜHE AUSGABE FÜR STATISCHE ASSETS
if (!empty($_GET['file'])) {
FV_AssetHandler::handle($_GET['file'], $_GET);
}
########################### DATEI AUSGEBEN (fvf) - KEINE SESSION NÖTIG
if (isset($_GET['fvf']) and !empty($_GET['fvf'])) {
if (FV_Core::$readInMode) {
$cleanPath = FV_FileSystem::normalizeRelativePath($_GET['fvf']);
if ($cleanPath === false) {
echo FV_Lang::get('invalidPath');
die();
}
$location = FV_FileSystem::secureFileLocation(FV_Core::scanDir() . $cleanPath);
if ($location) {
$par = FV_FileSystem::readFileInfo($location, 0);
header('Content-Disposition: inline; filename="' . $par['fileName'] . '"');
header('Content-Length: ' . $par['fileSize']);
if (!empty($par['mimeInfoFilter']['contentType'])) {
header('Content-Type: ' . $par['mimeInfoFilter']['contentType']);
}
readfile($location);
} else {
echo FV_Lang::get('invalidUrl');
}
} else {
echo FV_Lang::get('notAllowed');
}
die();
}
########################### BILD AUSGEBEN (fvi) - KEINE SESSION NÖTIG
if (isset($_GET['fvi']) and !empty($_GET['fvi'])) {
$cleanPath = FV_FileSystem::normalizeRelativePath($_GET['fvi']);
if ($cleanPath === false) {
die();
}
$location = FV_FileSystem::secureFileLocation(FV_Core::scanDir() . $cleanPath);
if ($location) {
$modus = ((isset($_GET['modus'])) ? (string) $_GET['modus'] : 'l');
$size  = ((isset($_GET['size'])) ? (int) $_GET['size'] : false);
$eImage = new FV_Image();
$eImage->imageFile($location)
->cacheDir(FV_Core::cacheDir() . substr(md5($location), 0, 1) . '/')
->modus($modus)
->size($size)
->quality(88);
if (!empty($_GET['webp']) and $_GET['webp'] == 'true' and str_contains($_SERVER['HTTP_ACCEPT'], 'image/webp')) {
$eImage->extension('webp');
}
if (FV_Core::$enableImageCache and FV_FileSystem::canWrite(true)) {
if (FV_Core::$thumbPixels == $size and $modus == 'q') {
$eImage->cache(true)->quality(15);
}
if (FV_Core::$enableImageBoost) {
$eImage->boost(true);
}
if (str_starts_with($location, FV_Core::cacheDir())) {
$eImage->undo('boost')->undo('cache');
}
}
FV_Helper::headerCache(60 * 60 * 24 * 7);
$eImage->get(false);
}
die();
}
########################### JETZT ERST SESSION FÜR HTML-AUSGABE
// Session-Sicherheit – Cookie-Flags setzen
session_set_cookie_params([
'lifetime' => 60 * 60 * 24 * 10,
'path' => FV_Core::insPath(),
'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
'httponly' => true,
'samesite' => 'Strict'
]);
session_start();
// CSRF-Token initialisieren (Helper kümmert sich drum)
FV_Helper::getCsrfToken();
setlocale(LC_ALL, 'de_DE.UTF-8', 'de_DE', 'de', 'ge');
mb_internal_encoding('UTF-8');
header('X-UA-Compatible: IE=edge');
FV_Helper::headerCache(2);
$site_message = [];
$site = [];
########################### LOGOUT
if (isset($_GET['logout'])) {
template_debug("Logout versucht");
if (FV_Core::$enableDemo) {
$site_message['error'][] = FV_Lang::get('logoutDemoNotPossible');
} else {
if (FV_Core::$autoLoggedIn) {
$site_message['error'][] = FV_Lang::get('logoutNotPossible');
} else {
if (isset($_SESSION['admin'])) {
unset($_SESSION['admin']);
}
// Session-ID regenerieren nach Logout
session_regenerate_id(true);
// CSRF-Token erneuern
$_SESSION['csrfToken'] = bin2hex(random_bytes(32));
template_debug("Logout erfolgreich");
}
}
}
########################### ADMIN
if (FV_Core::$enableDemo) {
$site['isLoggedAsAdmin'] = null;
} else {
if (FV_Core::$autoLoggedIn) {
$site['isLoggedAsAdmin'] = true;
} else {
if (
(isset($_SESSION['admin']['key']) and $_SESSION['admin']['key'] == hash('sha384', FV_Core::insDir() . FV_Core::$adminPassword))
and
(isset($_SESSION['admin']['timestamp']) and time() - $_SESSION['admin']['timestamp'] <= (60 * 30))
) {
$site['isLoggedAsAdmin'] = true;
$_SESSION['admin']['timestamp'] = time();
} else {
if (isset($_SESSION['admin'])) {
unset($_SESSION['admin']);
}
$site['isLoggedAsAdmin'] = false;
}
}
}
template_debug("isLoggedAsAdmin: " . ($site['isLoggedAsAdmin'] ? 'true' : ($site['isLoggedAsAdmin'] === null ? 'null' : 'false')));
########################### ORDNER
if (isset($_GET['fvd'])) {
if (FV_Core::pathList() === null) {
header('location: ' . FV_Core::insPath(true));
die();
}
}
########################### CACHE-VERZEICHNIS
if (FV_Core::$enableImageCache or FV_Core::$enableStructureCache) {
$dir  = FV_Core::cacheDir(false);
if ($dir && !is_dir($dir)) {
if (FV_FileSystem::createDir($dir)) {
$site_message['success'][] = FV_Lang::get('cacheFolderCreated', $dir);
} else {
$site_message['error'][] = FV_Lang::get('cacheFolderFailed', $dir);
}
}
}
########################### POST-AKTIONEN (Login, Upload, Delete)
if ($debugTemplate && $_SERVER['REQUEST_METHOD'] === 'POST') {
error_log("[template] ========== POST REQUEST ==========");
error_log("[template] POST Keys: " . implode(', ', array_keys($_POST)));
error_log("[template] POST action Wert: '" . ($_POST['action'] ?? 'nicht gesetzt') . "'");
error_log("[template] POST data: " . print_r($_POST['data'] ?? [], true));
}
$actionHandler = new FV_ActionHandler($site, $site_message);
if (isset($_POST['loginSubmit'])) {
template_debug("Login Submit erkannt");
$actionHandler->handleLogin($_POST['password'] ?? '');
$site_message = $actionHandler->getMessages();
template_debug("Login Messages: " . print_r($site_message, true));
}
if (isset($_POST['uploadSubmit'])) {
template_debug("Upload Submit erkannt");
$actionHandler->handleUpload($_FILES['file'] ?? [], $_POST['dir'] ?? '');
$site_message = $actionHandler->getMessages();
template_debug("========== UPLOAD RESULT ==========");
template_debug("Upload Messages: " . print_r($site_message, true));
template_debug("Upload success count: " . (isset($site_message['success']) ? count($site_message['success']) : 0));
template_debug("Upload error count: " . (isset($site_message['error']) ? count($site_message['error']) : 0));
}
if (isset($_POST['action']) && $_POST['action'] === FV_ActionHandler::ACTION_DELETE) {
template_debug("Delete Submit erkannt, data: " . print_r($_POST['data'] ?? [], true));
$actionHandler->handleDelete($_POST['data'] ?? []);
$site_message = $actionHandler->getMessages();
template_debug("========== DELETE RESULT ==========");
template_debug("Delete Messages: " . print_r($site_message, true));
template_debug("Delete success count: " . (isset($site_message['success']) ? count($site_message['success']) : 0));
}
if ($debugTemplate && $_SERVER['REQUEST_METHOD'] === 'POST') {
error_log("[template] ========== NACH HANDLERN ==========");
error_log("[template] site_message nach Handlern: " . print_r($site_message, true));
}
########################### SORTIERUNG
if (!isset($_SESSION['sort'])) {
$_SESSION['sort']['by']  = FV_UI::SORT_BY_NAME;
$_SESSION['sort']['dir'] = 'a';
$_SESSION['sort']['bd']  = $_SESSION['sort']['by'] . '-' . $_SESSION['sort']['dir'];
}
if (!empty($_GET['sort'])) {
$get = strtolower(trim((string) $_GET['sort']));
$allowedSorts = [
FV_UI::SORT_BY_NAME . '-a', FV_UI::SORT_BY_NAME . '-z',
FV_UI::SORT_BY_SIZE . '-a', FV_UI::SORT_BY_SIZE . '-z',
FV_UI::SORT_BY_DATE . '-a', FV_UI::SORT_BY_DATE . '-z',
FV_UI::SORT_BY_TYPE . '-a', FV_UI::SORT_BY_TYPE . '-z'
];
if (in_array($get, $allowedSorts)) {
$_SESSION['sort']['by']  = substr($get, 0, 4);
$_SESSION['sort']['dir'] = substr($get, 5, 1);
$_SESSION['sort']['bd']  = $_SESSION['sort']['by'] . '-' . $_SESSION['sort']['dir'];
}
}
########################### ANSICHT
if (!isset($_SESSION['view'])) {
$_SESSION['view'] = FV_UI::VIEW_AUTO;
}
if (!empty($_GET['view'])) {
$view = strtolower(trim((string) $_GET['view']));
if (in_array($view, [FV_UI::VIEW_AUTO, FV_UI::VIEW_CONSUMER, FV_UI::VIEW_PHOTOGRAPHER, FV_UI::VIEW_IT])) {
$_SESSION['view'] = $view;
}
}
########################### VARIABLEN
$rd = FV_Core::scanDir() . FV_Core::pathList('currentPath');
$detail = 1;
if (
in_array($_SESSION['view'], [FV_UI::VIEW_IT])
or
$rd == FV_Core::cacheDir()
) {
$detail = 0;
} else if (
in_array($_SESSION['view'], [FV_UI::VIEW_PHOTOGRAPHER])
) {
$detail = 2;
}
if (FV_Core::$requireLoginForRead && !$site['isLoggedAsAdmin']) {
$readedList = [];
} else {
$readedList = FV_FileSystem::readDirInfo($rd, $detail);
}
if(empty($readedList)){
$readedList = [
'totalSize'    => 0,
'totalCount'   => 0,
'dirList'      => [],
'fileList'     => [],
'dirListInfo'  => ['count' => 0],
'fileListInfo' => ['count' => 0, 'fileSize' => 0, 'fileSizeFormated' => '0 B']
];
}
$result = FV_UI::buildListItems($readedList, [
'currentPath'               => FV_Core::pathList('currentPath'),
'sortBy'                    => $_SESSION['sort']['by'],
'sortDir'                   => $_SESSION['sort']['dir'],
'view'                      => $_SESSION['view'],
'showCacheFolder'           => FV_Core::$showCacheFolder,
'showHideExtensionsInfo'    => FV_Core::$showHideExtensionsInfo
]);
$listItems = $result['listItems'];
$stats = $result['stats'];
template_debug("Anzahl ListItems: " . count($listItems));
template_debug("Stats: " . print_r($stats, true));
template_debug("========== VOR HTML AUSGABE ==========");
template_debug("site_message vor HTML: " . print_r($site_message, true));
template_debug("site_message count: " . count($site_message));
template_debug("success count: " . (isset($site_message['success']) ? count($site_message['success']) : 0));
?><!DOCTYPE html>
<html lang="de">
<head>
<title>
<?php
if (empty(FV_Core::pathList('currentName'))) {
echo $_SERVER['HTTP_HOST'];
} else {
echo implode(' > ', FV_Core::pathList());
}
?>
</title>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<meta name="robots" content="noindex,nofollow,noarchive" />
<meta name="googlebot" content="noarchive" />
<meta name="viewport" content="width=device-width, user-scalable=0" />
<meta name="theme-color" content="#94acb5" />
<meta property="og:image" content="<?= htmlentities(FV_Core::getLogoUrl()) ?>" />
<link rel="shortcut icon" type="image/png" href="<?= htmlentities(FV_Core::getFaviconUrl()) ?>" sizes="16x16" />
<link rel="icon" href="<?= htmlentities(FV_Core::getLogoUrl()) ?>" sizes="144x144 192x192 240x240" />
<link rel="apple-touch-icon" href="<?= htmlentities(FV_Core::getLogoUrl()) ?>" sizes="240x240" />
<link rel="stylesheet" href="<?= htmlentities(FV_Core::insPath(true) . '?file=css') ?>" type="text/css" />
<?php
$customCss = FV_Core::getCustomCssUrl();
if (!empty($customCss)) {
echo '<link rel="stylesheet" href="' . htmlentities($customCss) . '" type="text/css" />';
}
?>
<script>
siteComposition = {};
siteComposition.startTime = new Date().getTime();
window.onload = function() {
siteComposition.duration = new Date().getTime() - siteComposition.startTime;
var tofix = 2;
if (siteComposition.duration > 1000) {
var tofix = 1;
}
console.log('siteComposition.duration: ' + ((siteComposition.duration / 1000).toFixed(tofix)) + ' sec');
}
</script>
<script src="<?= htmlentities(FV_Core::insPath(true) . '?file=lang&lang=' . FV_Lang::language()) ?>"></script>
<script src="<?= htmlentities(FV_Core::insPath(true) . '?file=js') ?>"></script>
<?php
// ========== MELDUNGEN AUSGEBEN ==========
template_debug("========== MELDUNGS-AUSGABE ==========");
template_debug("count(site_message) >= 1? " . (count($site_message) >= 1 ? 'JA' : 'NEIN'));
if (count($site_message) >= 1) {
template_debug("GEBE MELDUNGEN AUS");
echo '<script>document.addEventListener(\'DOMContentLoaded\', () => {';
$delay = 300;
if (isset($site_message['success']) and count($site_message['success']) >= 1) {
template_debug("GEBE success Meldungen: " . print_r($site_message['success'], true));
foreach ($site_message['success'] as $messageContent) {
echo 'notify(\'' . addslashes($messageContent) . '\',\'success\', ' . $delay . ');';
$delay = $delay + 2000;
}
}
if (isset($site_message['info']) and count($site_message['info']) >= 1) {
template_debug("GEBE info Meldungen: " . print_r($site_message['info'], true));
foreach ($site_message['info'] as $messageContent) {
echo 'notify(\'' . addslashes($messageContent) . '\',\'info\', ' . $delay . ');';
$delay = $delay + 2000;
}
}
if (isset($site_message['error']) and count($site_message['error']) >= 1) {
template_debug("GEBE error Meldungen: " . print_r($site_message['error'], true));
foreach ($site_message['error'] as $messageContent) {
echo 'notify(\'' . addslashes($messageContent) . '\',\'error\', ' . $delay . ');';
$delay = $delay + 2000;
}
}
echo '});</script>';
} else {
template_debug("KEINE MELDUNGEN - site_message ist leer");
}
?>
</head>
<body style="background:rgba(0, 38, 49, 1);">
<div id="page">
<div id="controler_out">
<div id="controler" role="navigation">
<?php
if (!empty(FV_Core::pathList('backUrl'))) {
echo '<a href="' . htmlentities(FV_Core::pathList('backUrl')) . '" aria-label="' . FV_Lang::get('prev') . '"><span class="ic" data-ic="angle-left"></span></a>';
} else {
echo '<a href="' . htmlentities(FV_Core::insPath(true)) . '" aria-label="' . FV_Lang::get('homePage') . '"><img src="' . htmlentities(FV_Core::getLogoUrl()) . '" alt="" /></a>';
}
?>
<label title="<?= FV_Lang::get('download') ?>" data-ic="download"><?= FV_Lang::get('download') ?><input type="checkbox" name="action" value="download" form="main-form" /></label>
<label title="<?= FV_Lang::get('share') ?>" data-ic="share-square-o"><?= FV_Lang::get('share') ?><input type="checkbox" name="action" value="share" form="main-form" /></label>
<?php
if (($site['isLoggedAsAdmin'] and FV_Core::$enableUpload) or $site['isLoggedAsAdmin'] === null) {
echo '<button title="' . FV_Lang::get('fileUpload') . '" data-ic="upload" popovertarget="upload-form">' . FV_Lang::get('upload') . '</button>';
}
if (($site['isLoggedAsAdmin'] and FV_Core::$enableDelete) or $site['isLoggedAsAdmin'] === null) {
echo '<label title="' . FV_Lang::get('delete') . '" data-ic="trash-o">' . FV_Lang::get('delete') . '<input type="checkbox" name="action" value="delete" form="main-form" /></label>';
}
if ($site['isLoggedAsAdmin'] !== false) {
if (!FV_Core::$autoLoggedIn) {
echo '<a class="abtn" title="' . FV_Lang::get('logout') . '" data-ic="sign-out" id="logout-button" href="' . htmlentities(FV_Core::insPath(true) . '?logout=true&amp;fvd=' . urlencode(FV_Core::pathList('currentPath'))) . '">' . FV_Lang::get('logout') . '</a>';
}
} else {
if (FV_Core::$enableDelete or FV_Core::$enableUpload or FV_Core::$requireLoginForRead) {
echo '<button class="abtn" title="' . FV_Lang::get('loginTo') . '" data-ic="sign-in" id="login-button" popovertarget="login-form">' . FV_Lang::get('login') . '</button>';
}
}
?>
</div>
</div>
<ul id="smart-message"></ul>
<div id="content">
<form id="sort-form" action="" method="get" role="form">
<input name="fvd" value="<?= htmlentities(FV_Core::pathList('currentPath')) ?>" type="hidden" />
<label aria-label="<?= FV_Lang::get('sorting') ?>" title="<?= FV_Lang::get('sorting') ?>">
<select name="sort">
<optgroup label="<?= FV_Lang::get('sorting') ?>">
<?php
$by = [
FV_UI::SORT_BY_NAME . '-a',
FV_UI::SORT_BY_NAME . '-z',
FV_UI::SORT_BY_DATE . '-a',
FV_UI::SORT_BY_DATE . '-z',
FV_UI::SORT_BY_SIZE . '-a',
FV_UI::SORT_BY_SIZE . '-z',
FV_UI::SORT_BY_TYPE . '-a',
FV_UI::SORT_BY_TYPE . '-z'
];
foreach ($by as $srt) {
$sel = ($_SESSION['sort']['bd'] === strtolower($srt)) ? 'selected="selected"' : '';
echo '<option value="' . $srt . '" ' . $sel . '>' . strtoupper($srt) . '</option>';
}
?>
</optgroup>
</select>
</label>
<label aria-label="<?= FV_Lang::get('view') ?>" title="<?= FV_Lang::get('view') ?>">
<select name="view">
<optgroup label="<?= FV_Lang::get('view') ?>">
<?php
$views = [
FV_UI::VIEW_AUTO,
FV_UI::VIEW_CONSUMER,
FV_UI::VIEW_PHOTOGRAPHER,
FV_UI::VIEW_IT
];
foreach ($views as $srt) {
$sel = ($_SESSION['view'] === strtolower($srt)) ? 'selected="selected"' : '';
echo '<option value="' . $srt . '" ' . $sel . '>' . strtoupper($srt) . '</option>';
}
?>
</optgroup>
</select>
</label>
</form>
<p class="path">
<?php
$bread = FV_Core::pathList('info');
if (!empty($bread)) {
echo '<a href="' . htmlentities(FV_Core::insPath(true)) . '" data-ic="home" aria-label="' . FV_Lang::get('homePage') . '"></a>';
foreach ($bread as $path) {
echo '<b data-ic="angle-right"></b><a href="' . htmlentities($path['currentUrl']) . '">' . htmlentities(FV_Helper::formatName($path['name'])) . '</a>';
}
} else {
echo '<a href="' . htmlentities(FV_Core::insPath(true)) . '" data-ic="home" aria-label="' . FV_Lang::get('homePage') . '"> ' . FV_Lang::get('homePage') . '</a>';
}
?>
</p>
<?php
if (FV_Core::$requireLoginForRead && !$site['isLoggedAsAdmin']) {
echo '<script>
document.addEventListener("DOMContentLoaded", function() {
var loginButton = document.getElementById("login-button");
if (loginButton) {
var popover = document.getElementById("login-form");
if (popover && popover.showPopover) {
popover.showPopover();
} else {
loginButton.click();
}
}
});
</script>';
} else {
// main-form umschließt die Liste
echo '<form id="main-form" method="post" action="">';
echo FV_Helper::csrfField();
echo FV_UI::renderUl($listItems);
echo '</form>';
}
?>
<div style="clear: both;"></div>
<?php
$site_composition['duration'] = microtime(true) - $site_composition['startTime'];
$siteLoadTime = number_format($site_composition['duration'], 2, '.', '');
?>
<p class="infobox" title="<?= $siteLoadTime ?> sec">
<?php
if (!empty($stats['totalFileCount'])) {
$hiddenCount = $stats['totalFileCount'] - $stats['visibleFileCount'];
echo '<span data-ic="file">' . $stats['totalFileCount'] . ' ' . (($stats['totalFileCount'] > 1) ? FV_Lang::get('files') : FV_Lang::get('file'));
if ($hiddenCount > 0) {
echo ' <i>(' . $hiddenCount . ' ' . FV_Lang::get('hidden') . ')</i>';
}
echo ': ' . FV_Helper::formatByte($stats['totalFileSize']) . '</span>';
}
if (!empty($stats['folderCount'])) {
echo '<span data-ic="folder">' . FV_Lang::get('$FolderWith$Files', $stats['folderCount'], $stats['folderFileCount']) . ' : ' . FV_Helper::formatByte($stats['folderFileSize']) . '</span>';
}
?>
</p>
</div>
<?php
if ($site['isLoggedAsAdmin'] or $site['isLoggedAsAdmin'] === null) {
echo '<button type="submit" class="abtn" id="main-submit" form="main-form">' . (($stats['folderFileCount'] == 1) ? FV_Lang::get('item') : FV_Lang::get('items')) . '</button>';
$name = FV_Core::pathList('currentName');
if (!empty($name)) {
$name = '<small>' . FV_Lang::get('inFolder') . ' "' . $name . '"</small>';
}
echo '
<form class="slayer" id="upload-form" action="" method="post" enctype="multipart/form-data" popover>
' . FV_Helper::csrfField() . '
<h1>' . FV_Lang::get('upload') . ' ' . $name . '</h1>
<input type="file" name="file[]" multiple="multiple" />
<input type="hidden" name="uploadSubmit" value="true" />
<input type="text" name="dir" placeholder="' . FV_Lang::get('optionalSubfolder') . '" pattern="[0-9A-Za-z]{0,}" class="small" />
<input value="' . FV_Lang::get('upload') . '" type="submit" />
</form>';
} else {
echo '
<form class="slayer" method="post" id="login-form" autocomplete="off" action="" popover>
' . FV_Helper::csrfField() . '
<h1>' . FV_Lang::get('login') . '</h1>
<input type="password" name="password" value="" placeholder="' . FV_Lang::get('password') . '" />
<input type="hidden" name="loginSubmit" value="true" />
<input type="submit" value="' . FV_Lang::get('login') . '" />
</form>';
}
?>
<div id="popover-backdrop"></div>
</div>
</body>
</html>
<?php
/*
########################################################
############## CONFIG FOR FILE.VIEWER ##################
############### THIS FILE IS OPTIONAL ##################
################# COPYRIGHT E2SEE.DE ###################
########################################################
define('_fvconfig', json_encode([
#'adminPassword'          => '\$argon2id\$v=19\$m=65536,t=4,p=1\$d0ZBQ1Fyd0h4aFJ2V3psOA\$VPcB6MFFtbqyhbNFE01F2k0fzufMrIeBGecvBy8ePYU',                  /* Default: 'yourPassword' - Change this! */
#'autoLoggedIn'           => false,                                       /* Default: false - true = always logged in as admin */
#'requireLoginForRead'    => false,                                       /* Default: false - true = login required to view files */
#'cacheDir'               => dirname(__FILE__).'/_fv-cache/',             /* Default: /_fv-cache/ in installation directory */
#'enableCaching'          => true,                                        /* Default: true - false = no caching (thumbnails + structure) */
#'enableImageCache'       => true,                                        /* Default: true (if enableCaching true) */
#'enableStructureCache'   => true,                                        /* Default: true (if enableCaching true) */
#'scanDir'                => dirname(__FILE__).'/',                       /* Default: installation directory - Alternative scan directory */
#'scanRootUrl'            => 'https://files.my.domain/',                  /* Only needed if scanDir changed: Public URL to scan directory */
#'enableDelete'           => false,                                       /* Default: false - true = delete enabled */
#'enableUpload'           => false,                                       /* Default: false - true = upload enabled */
#'readInMode'             => false,                                       /* Default: false - true = files are streamed through PHP (hides real paths) */
#'hideExtensions'         => '., !jpg, !mp3',                             /* Default: security-relevant types */
#'hideExtensionsAdd'      => 'conf, yml',                                 /* Default: none - Example: hide additional types */
#'showHideExtensionsInfo' => true,                                        /* Default: true - false = hide info about hidden files */
#'ignoreItems'            => '\$RECYCLE.BIN, System Volume Information, lost+found', /* Default system folders to ignore */
#'showCacheFolder'        => true,                                        /* Default: true - false = hide cache folder from file list */
#'enableMimeValidation'   => true,                                        /* Default: false - true = check MIME type on upload (requires PHP finfo) */
#'mimeTypeMappingAdd'     => 'heic:image/heic, svg:image/svg+xml',        /* add additional allowed formats */
#'mimeTypeMappingAdd'     => '!php, !exe, !bat',                          /* block specific file types */
#'mimeTypeMappingAdd'     => '!jpg, webp:image/webp, svg:image/svg+xml',  /* block JPG, allow webp and svg */
#'enableDemo'             => false,                                       /* Default: false - true = demo mode (looks like logged in) */
#'enableImageBoost'       => false,                                       /* Default: false - true = optimized image versions (needs cache) */
#'thumbPixels'            => 360,                                         /* Default: 360px - Thumbnail size */
#'imagePixels'            => 1200,                                        /* Default: 1200px - Lightbox image size */
#'language'               => 'de',                                        /* Default: de - Available: de, en, tr */
#'customLogoUrl'          => '',                                          /* Default: '' - custom logo URL (e.g. '/my-logo.png') */
#'customFaviconUrl'       => '',                                          /* Default: '' - custom favicon URL (e.g. '/favicon.ico') */
#'customCssUrl'           => '',                                          /* Default: '' - custom CSS URL to override styles (e.g. '/custom.css') */
#'debug'                  => false,                                       /* Default: false - Example: 'fv,actionHandler' */
#]));
?>
