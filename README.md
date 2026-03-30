# E2 FileViewer – One File, Two Worlds: Gallery & File Explorer

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**One single PHP file – and your FTP folder becomes a stylish, responsive gallery or a classic file explorer.**  
No installation, no database, no generating HTML files. Just upload the file, and the viewer immediately recognises your folder structure and displays both media and documents like PDFs, Office files, or archives in a clear layout.





◤◤◤ What's inside?

- **📦 One file – no clutter** – Developers love modules, users love simplicity. No dependencies, no folder chaos.
- **🖼️ Gallery & explorer in one** – For images, music and videos you get an integrated lightbox; for PDFs, Office files and other documents a classic list view with thumbnails.
- **🔒 Secure login** – Password protection with modern methods (Argon2id, SHA256 or plaintext). Enable it in the config.
- **⬆️ Upload & 🗑️ Delete** – Optionally enable with CSRF protection and security checks.





◤◤◤ Why E2 FileViewer?

Because you shouldn't have to care about installation, databases, or complex setups.  
Drop the file, open it, and you're done. No configuration? Works right away. Want fine‑grained control? The optional config file lets you tweak everything.





◤◤◤ Technical Highlights

- **⚡ Smart caching** – Thumbnail caching plus structure caching with snapshot hashes. Even with thousands of files, browsing stays fast.
- **🚀 ImageBoost** – Multi‑stage pre‑scaling of thumbnails saves CPU time and makes scrolling buttery smooth.
- **🔄 Choose your delivery** – Files can be served directly by the web server or streamed through PHP – flexible for permission checks or logging.
- **📂 Custom scan directory** – Point the viewer to any folder outside the webroot and set the public URL via `scanRootUrl` – ideal for separating storage and output paths.
- **🧩 Custom branding** – Replace logo, favicon and CSS – visually adapt without touching code.
- **🛡️ Granular visibility** – Hide specific file extensions (blacklist or whitelist). System folders like `$RECYCLE.BIN` are ignored automatically.





◤◤◤ Installation

1. Download the file – it’s distributed as `e2fileviewer.php` (or grab the latest release).
2. Upload it to any folder on your web server.
3. **Rename it to `index.php` if you want it to be the default page for that folder** – the viewer will then open automatically when visiting the directory.
4. Open it in your browser – you’re done.

That’s it. No other files, no dependencies.

Optionally, create a `_fv-config.php` next to the file to adjust settings (see comments inside the file).



◤◤◤ Configuration (optional)

Create a file named `_fv-config.php` next to `index.php` and fill it with your settings.  
Here’s a typical example:

```php
define('_fvconfig', json_encode([
    // Security – use a strong password hash (see generator below)
    'adminPassword'          => '$argon2id$v=19$m=65536,t=4,p=1$d0ZBQ1Fyd0h4aFJ2V3psOA$VPcB6MFFtbqyhbNFE01F2k0fzufMrIeBGecvBy8ePYU',

    // Enable upload and delete (with CSRF protection)
    'enableUpload'           => true,
    'enableDelete'           => true,

    // Hide system files, but keep JPG and PNG visible
    'hideExtensions'         => '., !jpg, !png',

    // Optional: scan a different folder, e.g. an external drive
    'scanDir'                => '/mnt/storage/public/',

    // Optional: custom logo and CSS
    'customLogoUrl'          => '/my-logo.png',
    'customCssUrl'           => '/custom.css'
]));
```

> **Password generator** – To create a secure password hash, uncomment one of the lines at the bottom of `_fv-config.php` and run the file once.

That’s it. The viewer works with or without config – all settings are optional.

**This example**:
- Uses a safe password hash (the one already in the file).
- Shows `enableUpload` and `enableDelete` as `true`.
- Demonstrates `hideExtensions` with exceptions (`., !jpg, !png`).
- Adds `scanDir` to show external folder support.
- Shows branding options (`customLogoUrl`, `customCssUrl`).
- Mentions the password generator comment inside the file.

It’s realistic, safe, and highlights several powerful features.
