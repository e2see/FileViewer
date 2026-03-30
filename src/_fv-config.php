<?php

/*
########################################################
############## CONFIG FOR FILE.VIEWER ##################
############### THIS FILE IS OPTIONAL ##################
################# COPYRIGHT E2SEE.DE ###################
########################################################
*/


define('_fvconfig', json_encode([

    ##### SECURITY

    #'adminPassword'          => '\$argon2id\$v=19\$m=65536,t=4,p=1\$d0ZBQ1Fyd0h4aFJ2V3psOA\$VPcB6MFFtbqyhbNFE01F2k0fzufMrIeBGecvBy8ePYU',                  /* Default: 'yourPassword' - Change this! */
    #'autoLoggedIn'           => false,                           /* Default: false - true = always logged in as admin */
    #'requireLoginForRead'    => false,                           /* Default: false - true = login required to view files */



    ##### CACHE

    #'cacheDir'               => dirname(__FILE__).'/_fv-cache/', /* Default: /_fv-cache/ in installation directory */
    'enableCaching'          => true,                             /* Default: true - false = no caching (thumbnails + structure) */
    #'enableImageCache'       => true,                            /* Default: true (if enableCaching true) */
    #'enableStructureCache'   => true,                            /* Default: true (if enableCaching true) */



    ##### FILE SYSTEM

    #'scanDir'                => dirname(__FILE__).'/',           /* Default: installation directory - Alternative scan directory */
    #'scanRootUrl'            => 'https://files.my.domain/',      /* Only needed if scanDir changed: Public URL to scan directory */
    'enableDelete'           => false,                             /* Default: false - true = delete enabled */
    'enableUpload'           => false,                             /* Default: false - true = upload enabled */
    #'readInMode'             => false,                           /* Default: false - true = files are streamed through PHP (hides real paths) */



    ##### FILE FILTERS

    // hideExtensions: Hide file types in the list
    //   - '.'          = hide all files
    //   - '!jpg, !png' = exceptions (do NOT hide)
    //   - 'exe, bat'   = hide only these types

    #'hideExtensions'         => '., !jpg, !mp3',                 /* Default: security-relevant types */
    #'hideExtensionsAdd'      => 'conf, yml',                     /* Default: none - Example: hide additional types */
    #'showHideExtensionsInfo' => true,                            /* Default: true - false = hide info about hidden files */
    #'ignoreItems'            => '\$RECYCLE.BIN, System Volume Information, lost+found', /* Default system folders to ignore */
    #'showCacheFolder'        => true,                            /* Default: true - false = hide cache folder from file list */



    ##### UPLOAD SECURITY

    'enableMimeValidation'   => true,                            /* Default: false - true = check MIME type on upload (requires PHP finfo) */

    // Additional MIME mappings or blocking rules
    // Format: 'extension:content-type' to allow specific types
    //         '!extension' to block (regardless of MIME type)
    // Examples (copy and uncomment):

    #'mimeTypeMappingAdd'     => 'heic:image/heic, svg:image/svg+xml',          /* add additional allowed formats */
    #'mimeTypeMappingAdd'     => '!php, !exe, !bat',                            /* block specific file types */
    #'mimeTypeMappingAdd'     => '!jpg, webp:image/webp, svg:image/svg+xml',    /* block JPG, allow webp and svg */



    ##### DISPLAY

    #'enableDemo'             => false,                           /* Default: false - true = demo mode (looks like logged in) */
    #'enableImageBoost'       => false,                           /* Default: false - true = optimized image versions (needs cache) */
    #'thumbPixels'            => 360,                             /* Default: 360px - Thumbnail size */
    #'imagePixels'            => 1200,                            /* Default: 1200px - Lightbox image size */
    #'language'               => 'de',                            /* Default: de - Available: de, en, tr */
    #'customLogoUrl'          => '',                              /* Default: '' - custom logo URL (e.g. '/my-logo.png') */
    #'customFaviconUrl'       => '',                              /* Default: '' - custom favicon URL (e.g. '/favicon.ico') */
    #'customCssUrl'           => '',                              /* Default: '' - custom CSS URL to override styles (e.g. '/custom.css') */



    ##### DEBUG (Development)

    // Debug mode for various components
    // Possible values:
    //   - false / ''                  : Debug off (recommended for production)
    //   - 'all'                       : Debug in all classes
    //   - 'fv_core'                   : only FV class (paths, scans, cache)
    //   - 'fv_structureCache'         : only StructureCache (JSON, snapshots)
    //   - 'fv_actionHandler'          : only ActionHandler (login, upload, delete)
    //   - 'fv_ui'                     : only UI class (HTML rendering)
    //   - 'fv_template'               : only Template (asset output, messages)
    //   - 'fv_core,fv_actionHandler'  : multiple, comma separated

    'debug'                  => false,                           /* Default: false - Example: 'fv,actionHandler' */

]));



########## PASSWORD GENERATOR ##########
########## UNCOMMENT ONE OF THE METHODS BELOW ##########

# ARGON2id (RECOMMENDED) - The most secure hash. Requires PHP 7.2+ and Argon2 support.
# echo password_hash('yourPassword', PASSWORD_ARGON2ID); die();

# SHA256 - Secure alternative if Argon2 is not available.
# echo hash('sha256', 'yourPassword'); die();

# BCRYPT - Good alternative, widely supported.
# echo password_hash('yourPassword', PASSWORD_BCRYPT); die();

# PLAIN TEXT (NOT RECOMMENDED) - Only for testing or if you know what you're doing.
# echo 'yourPassword'; die();


?>
