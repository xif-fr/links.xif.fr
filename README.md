# links.xif.fr

Minimalistic web-based folder-ed links/documents/knowledge manager

## Examples
See <https://l.xif.fr/>.

<img src="https://www.xif.fr/screen-links-xif-fr.png" width="800">

## Features

## Usage

 * To install, simply copy the content of `_default_` in the root directory.
 * If authentication is needed, then move `auth.json` somewhere inaccessible by the http server (e.g. in `/etc/webapps/whatever-you-choose/auth.json`) and edit `$_CONF['authfilepath']` in `conf.php` accordingly.
 * Modify `conf.php` as wanted, especially `$_CONF['private-repository']` and `$_CONF['titlebase']`.

Files `ytdl.py` and `video.php`, `scripts.php`, `nojs.php`, `auth.php` (if `public-edit=true` in auth.json), `doc.txt`, `ytchannels.php` can be deleted without problems if minimalism is wanted.
