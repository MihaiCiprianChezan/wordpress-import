# Wordpress Import Plugin

The **Wordpress Import** Plugin allows you to import xml posts data, including attachments media files (images eg. JPG, PNG, PDFs, ZIP archives etc.) exported from a WordPress blog into a GRAV blog via a command line interface.

** !!! This is still work in progress, please wait before using  until stable version is released (a few days) !!! **

This plugin is for [Grav CMS](http://github.com/getgrav/grav).

### Contents
1. [Installation](#installation)
2. [Usage](#usage)

## Installation

Installing the Wordpress Import plugin can be done in one of two ways. The GPM (Grav Package Manager) installation method enables you to quickly and easily install the plugin with a simple terminal command, while the manual method enables you to do so via a zip file.

### GPM Installation (Preferred)

The simplest way to install this plugin is via the [Grav Package Manager (GPM)](http://learn.getgrav.org/advanced/grav-gpm) through your system's terminal (also called the command line).  From the root of your Grav install type:

    bin/gpm install wordpress-import

This will install the Wordpress Import plugin into your `/user/plugins` directory within Grav. Its files can be found under `/your/site/grav/user/plugins/wordpress-import`.

### Manual Installation

To install this plugin, just download the zip version of this repository and unzip it under `/your/site/grav/user/plugins`. Then, rename the folder to `wordpress-import`. You can find these files on [GitHub](https://github.com/zacchaeus/grav-plugin-wordpress-import) or via [GetGrav.org](http://getgrav.org/downloads/plugins#extras).

You should now have all the plugin files under

    /your/site/grav/user/plugins/wordpress-import

> NOTE: This plugin is a modular component for Grav which requires [Grav](http://github.com/getgrav/grav) and the [Error](https://github.com/getgrav/grav-plugin-error) and [Problems](https://github.com/getgrav/grav-plugin-problems) to operate.

It also requires the PHP extension SimpleXML to be enabled (enabled by default in PHP>=5.1.2).

## Usage

This plugin is used entirely though the command line.

You will need an exported wordpress xml file. Instructions are available for exporting from   [Wordpress.com](https://support.wordpress.com/export/) and for [wordpress.org](https://codex.wordpress.org/Tools_Export_Screen).

Additionally you will need to know the Folder Name for your blog into which you would like to import posts. This can be found via the Admin Panel -> Pages -> Your Blog -> Advanced tab -> Folder Name. Or by looking in your `grav-install/user/pages` folder.

The only command available is `import`. This should be run from the command line inside your grav root directory.

    bin\plugin wordpress-import import <file> <blog>

### Arguments

| Argument | Descripton |
|------|-------------------------------|
| file | The xml wordpress export file |
| blog | The folder name of your blog |

### Options
| Option | Longform         | Descripton |
|--------|-------------|----------------------|
| -c | --cat=CAT | Add an additional category to all posts |
| -u | --uncat | Remove the category "Uncategorized" from all posts |
| -o | --overwrite |Force overwrite blog posts with the same name if they already exist.
| -m | --mediafolder MEDIAFOLDER | Media folder name (eg. media-folder) for files which don't have parent posts or are not attached to certain posts. |

> NOTE: If the overwrite option is not selected then any duplicate posts will prompt you to ask if you wish to overwrite.

### Examples

1. Import posts from `path/to/wordpress-export.xml` into `my-blog`

        bin\plugin wordpress-import import path/to/wordpress-export.xml my-blog

2. Import posts as above, but force overwriting, remove "Uncategorized" and add the category `blog` to all posts

        bin\plugin wordpress-import import -o -u -c blog `path/to/wordpress-export.xml` `my-blog`
        
3. Import posts and specify media attachments folder for attachments which don't have parent posts 
        php bin\plugin wordpress-import import `path/to/wordpress-export.xml` `my-blog` --mediafolder media
