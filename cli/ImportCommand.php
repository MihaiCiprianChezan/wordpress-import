<?php

namespace Grav\Plugin\Console;

use Grav\Console\ConsoleCommand;
use RocketTheme\Toolbox\File\File;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Yaml\Yaml;

include(dirname(__FILE__) . '/../lib/simplehtmldom/simple_html_dom.php'); // fixed \ folder separator 01.03.19

/**
 * Class HelloCommand
 *
 * @package Grav\Plugin\Console
 */
class ImportCommand extends ConsoleCommand
{
    /**
     * @var array
     */
    protected $options = [];

    protected function configure()
    {
        $this
            ->setName("import")
            ->setDescription("Imports a wordpress file into a blog")
            ->addArgument(
                'file',
                InputArgument::REQUIRED,
                'The wordpress export file'
            )
            ->addArgument(
                'blog',
                InputArgument::REQUIRED,
                'The name of the blog to import into'
            )
            ->addOption(
                'cat',
                'c',
                InputOption::VALUE_REQUIRED,
                'Add an additional category to all posts'
            )
            ->addOption(
                '--uncat',
                'u',
                InputOption::VALUE_NONE,
                'Remove the category "Uncategorized" from all posts'
            )
            ->addOption(
                '--overwrite',
                'o',
                InputOption::VALUE_NONE,
                'Force overwrite blog posts with the same name if they already exist'
            )
            ->addOption(
                'mediafolder',
                'm',
                InputOption::VALUE_REQUIRED,
                'Media folder name (eg. media-folder) for files which don\'t have parent posts or are not attached to certain posts.',
                '_media-folder'
            )
            ->addOption(
                '--displaymeta',
                'd',
                InputOption::VALUE_NONE,
                'Displays/lists/prints meta keys and values'
            )
            ->setHelp('The <info>import</info> imports a wordpress export into a blog of your choosing.');
    }

    /**
     * @return int|null|void
     */
    protected function serve()
    {
        // Configures php enviroment for the script to be able to run
        function configure_errors_ini_settings()
        {
            ini_set('memory_limit', '3000M');
            ini_set('post_max_size', "30M");
            ini_set('upload_max_filesize', "30M");
            var_dump(libxml_use_internal_errors(true));
            ini_set('allow_url_fopen', true);
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
            error_reporting(E_ALL);
            $cacert_replative_path = preg_windows_slashes('\user\plugins\wordpress-import\ssl\cacert-2019-01-23.pem', "\\");
            $cacert = getcwd() . $cacert_replative_path;
            if (!ini_get('curl.cainfo')) {
                if (file_exists($cacert)) {
                    echo "--- Will use CA certificates from Mozilla " . $cacert . "\n";
                    ini_set('curl.cainfo', $cacert);
                    ini_set('openssl.cafile', $cacert);
                    echo "--- If you get errors while saving attachments set/add in php.ini:" . "\n\n"
                        . "     curl.cainfo=\"" . $cacert . "\"\n"
                        . "     openssl.cafile=\"" . $cacert . "\"\n\n";
                } else {
                    echo "--- Error cacert-2019-01-23.pem doesn't exist, remote saving of attachments with curl will not work." . "\n";
                }
            }
        }

        // Makes a string (file path) compatible with windows paths if windows systems is detected
        function preg_windows_slashes($string_value, $rep_value = "\\\\")
        {
            if (strtoupper(PHP_OS == "WINDOWS") || strtoupper(PHP_OS) == 'WINNT') {
                $string_value = str_replace('/', $rep_value, $string_value);
            }
            return $string_value;
        }

        // Saves an image or an object/item/file from url with curl
        function curl_save_image($url, $saveto)
        {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPGET, 1);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_DNS_USE_GLOBAL_CACHE, false);
            curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 2);
            if (!$raw = curl_exec($ch)) {
                trigger_error(curl_error($ch));
                echo('--- curl_exec Error:' . curl_error($ch) . "\n");
            }
            curl_close($ch);
            if (file_exists($saveto)) {
                unlink($saveto);
            }
            if (!$fp = fopen($saveto, 'x')) {
                echo('--- fopen Error:' . $saveto . ' could not be open.' . "\n");
            }
            if (!fwrite($fp, $raw)) {
                echo('--- fwrite Error:' . $saveto . ' could not be written.' . "\n");
            }
            if (!fclose($fp)) {
                echo('--- fclose Error:' . $saveto . ' could not be closed.' . "\n");
            }
            if (file_exists($saveto)) return true;
            else return false;
        }

        // Displays XML parsing errors
        function display_xml_error($error, $xml)
        {
            $return = $xml[$error->line - 1] . "\n";
            $return .= str_repeat('-', $error->column) . "^\n";
            switch ($error->level) {
                case LIBXML_ERR_WARNING:
                    $return .= "Warning $error->code: ";
                    break;
                case LIBXML_ERR_ERROR:
                    $return .= "Error $error->code: ";
                    break;
                case LIBXML_ERR_FATAL:
                    $return .= "Fatal Error $error->code: ";
                    break;
            }
            $return .= trim($error->message) .
                "\n  Line: $error->line" .
                "\n  Column: $error->column";

            if ($error->file) {
                $return .= "\n  File: $error->file";
            }
            return "$return\n\n--------------------------------------------\n\n";
        }

        function preg_slug($stringval, $post_parent_title)
        {
            if ($stringval == '') {
                $stringval = str_replace(' ', '-', $post_parent_title);
                $stringval = strtolower(preg_replace('/[^A-Za-z0-9\-]/', '', $stringval));
            }
            return $stringval;
        }

        function add_variable_operators($str)
        {
            if (strpos($str, '?') === false) {
                $str .= '?';
            } else {
                $str .= '&';
            }
            return $str;
        }

        // Displays import file status/info and other info
        if (function_exists('simplexml_load_file')) {
            echo "--- simpleXML functions are available.\n";
        } else {
            echo("--- simpleXML functions are not available" . "\n");
            echo("--- simpleXML functions are requred to parse XML file" . "\n");
            echo("--- Note: PHP default installation has simpleXML enabled by default" . "\n");
            echo("--- Note: For info please reffer to http://php.net/manual/ro/book.simplexml.php" . "\n");
            echo("--- Warning: Please make sure you have simpleXML enabled in your PHP enviroment." . "\n");
            throw new \RuntimeException ('');
        }

        echo "--- current working  directory " . getcwd() . "\n";
        if (file_exists($this->options['file'])) {
            if (is_readable($this->options['file'])) {
                $is_readable = 'is readable.' . "\n";
            } else {
                $is_readable = 'is not readable' . "\n";
                throw new \RuntimeException ('--- Error: ' . $this->options['file'] . ' exists and ' . $is_readable . "\n");
            }
            echo('--- ' . $this->options['file'] . ' exists and ' . $is_readable . "\n");
        }

        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
            echo '--- connection is HTTPS' . "\n";
        } else {
            echo '--- connection is HTTP' . "\n";
        }

        configure_errors_ini_settings();

        // Collect the arguments and options as defined
        $this->options = [
            'file' => $this->input->getArgument('file'),
            'blog' => $this->input->getArgument('blog'),
            'cat' => $this->input->getOption('cat'),
            'uncat' => $this->input->getOption('uncat'),
            'overwrite' => $this->input->getOption('overwrite'),
            'mediafolder' => $this->input->getOption('mediafolder'),
            'displaymeta' => $this->input->getOption('displaymeta')
        ];

        if ($this->options['mediafolder'] == '') {
            $media_folder = '_media-folder';
        } else {
            $media_folder = $this->options['mediafolder'];
        }

        $display_meta = $this->options['displaymeta'];
        $overwrite_all = $this->options['overwrite'];
        $exist_count = 0;

        $bloglocation = PAGES_DIR . $this->options['blog'];
        if (!file_exists($bloglocation . '/blog.md')) {
            throw new \RuntimeException('Could not find blog at location: ' . $bloglocation . "\n");
        } else {
            $this->output->writeln("Adding posts to " . $bloglocation . "\n");
        }

        //validate file input
        if (file_exists($this->options['file'])) {
            $xml = simplexml_load_file($this->options['file']);
            if ($xml === false) {
                $errors = libxml_get_errors();
                $errorsCounter = 1;
                foreach ($errors as $error) {
                    echo '--- Error ' . $errorsCounter . ' ---' . display_xml_error($error, $xml);
                    $errorsCounter++;
                }
                libxml_clear_errors();
                throw new \RuntimeException('Could not load xml file ' . $this->options['file'] . "\n");
            }
        } else {
            throw new \RuntimeException('Could not find file: ' . $this->options['file'] . "\n");
        }

        $post_count = 0;

        foreach ($xml->channel->item as $item) {
            //
            //check its actually a post --------------------------------------------------------------------------------
            //
            if ((string)$item->children('wp', true)->post_type == 'post') {

                $title = (string)$item->title;
                $date = (string)$item->pubDate;
                $content = (string)$item->children('content', true)->encoded;
                $published = ((string)$item->children('wp', true)->status == 'publish') ? true : false;
                $post_id = (string)$item->children('wp', true)->post_id;
                $slug = (string)$item->children('wp', true)->post_name;

                echo "\n--- post_id: " . $post_id . ", post_title: " . $title . "\n";
                preg_match_all('/<img[^>]+>/i', $content, $content_images);
                echo "      inline/intext images: " . count($content_images[0]) . "\n";
                preg_match_all('/<a[^>]+>/i', $content, $content_links);
                echo "      inline/intext links: " . count($content_links[0]) . "\n";

                // Process meta data
                if (!empty($item->children('wp', true)->postmeta)) {
                    if ($display_meta) echo '--- post meta data: ' . "\n";
                } else {
                    echo '--- post has no meta data.' . "\n";
                }

                $has_thumbnail = false;
                $meta_data = [];
                $meta_data["generator"] = 'kubusmedia';
                $meta_data["description"] = $title;

                foreach ($item->children('wp', true)->postmeta as $meta) {
                    $meta_key = (string)$meta->meta_key;
                    $meta_value = (string)$meta->meta_value;

                    $meta_dummy = array(
                        '_thumbnail_id'
                    );


                    if ($meta_value != '' && !in_array($meta_key, $meta_dummy)) {
                        $meta_data[$meta_key] = $meta_value;
                    }

                    if ($display_meta) {
                        echo '       meta_key: ' . $meta_key . ", ";
                        echo 'meta_value: ' . $meta_value . "\n";
                    }

                    if ($meta->meta_key == '_thumbnail_id') {
                        $has_thumbnail = true;
                        $thumbnail_attachment_id = (string)$meta->meta_value;
                    }
                }

                // Process tags and categories
                $tags = [];
                $categories = [];

                foreach ($item->category as $cat) {
                    switch ((string)$cat['domain']) { // Get category attributes as element indices
                        case 'post_tag':
                            array_push($tags, (string)$cat);
                            break;
                        case 'category':
                            if ($this->options['uncat'] && (string)$cat = "Uncategorized") {
                                break;
                            }
                            array_push($categories, (string)$cat);
                            break;
                    }
                }

                if ($this->options['cat']) {
                    array_push($categories, $this->options['cat']);
                }

                // Process file names and folders
                $slug = preg_slug($slug, $title);
                $filename = $bloglocation . '/' . $slug . '/item.md';

                if ($overwrite_all == false && file_exists($filename)) {
                    $this->output->writeln("\n" . "The post with slug '" . $slug . "' already exists in " . $bloglocation . "\n");
                    $helper = $this->getHelper('question');
                    $question = new ConfirmationQuestion('Do you wish to overwrite it?' . "\n", false);

                    if (!$helper->ask($this->input, $this->output, $question)) {
                        continue;
                    }
                }

                $file = File::instance($filename);

                $frontmatter = array(
                    'title' => $title,
                    'published' => $published,
                    'date' => $date,
                    'taxonomy' => array(
                        'category' => $categories,
                        'tag' => $tags
                    ),
                    'visible' => true,
                    'metadata' => $meta_data,
                );

                // save attachments, who have parent posts

                $parent_post = $post_id;
                $parent_slug = $slug;
                $media_attachements = [''];
                $attachment_log = '';

                if ($has_thumbnail) {
                    $attachment_log .= '       post has thumbnail/header image with id:' . $thumbnail_attachment_id . "\n";
                    $attachment_log .= '       trying to save and associate attachment.' . "\n";
                    $header_image_save_name = '';
                }

                foreach ($xml->channel->item as $item_attachment) {
                    if ((string)$item_attachment->children('wp', true)->post_parent == $parent_post) {

                        $title_attachment = (string)$item_attachment->title;
                        $post_id_attachment = (string)$item_attachment->children('wp', true)->post_id;
                        $date_attachment = (string)$item_attachment->pubDate;
                        $link_attachment = (string)$item_attachment->link;
                        $slug_attachment = (string)$item_attachment->children('wp', true)->post_name;
                        $attachment_url = (string)$item_attachment->children('wp', true)->attachment_url;

                        $post_parent_attachment = (string)$item_attachment->children('wp', true)->post_parent;
                        $attachment_log .= "------ attachment post_id: " . $post_id_attachment . ", post_parent_id: " . $post_parent_attachment . "\n";
                        $attachment_log .= "       attachment link: " . $link_attachment . "\n";
                        $attachment_log .= "       attachment link: " . $attachment_url . " (URL) \n";
                        $attachment_log .= "       attachment title: " . $title_attachment . "\n";

                        $slug_attachment = $parent_slug; //preg_slug($slug_attachment, $parent_title);
                        $attachment_url = (string)$item_attachment->children('wp', true)->attachment_url;
                        $attachment_extension = strtolower(pathinfo($attachment_url, PATHINFO_EXTENSION));
                        $attachment_file_name = basename($attachment_url);
                        $filename_attachment = $attachment_file_name;

                        if ($post_id_attachment == $thumbnail_attachment_id && $has_thumbnail) {
                            $header_image_save_name = $attachment_file_name;
                            $frontmatter['header_image'] = (string)$header_image_save_name;
                            $media_attachements[0] = $header_image_save_name;
                        } else {
                            $header_image_save_name = '';
                            $media_attachements[] = $attachment_file_name;
                        }

                        $foldername_attachment = $bloglocation . '/' . $slug_attachment;
                        $foldername_info = $foldername_attachment . '/';

                        if (!file_exists($foldername_attachment)) {
                            if (!mkdir($foldername_attachment)) {
                                $attachment_log .= "--- Error: failed to create folder: " . $foldername_info . "\n";
                            }
                        }

                        $foldername_attachment = preg_windows_slashes($foldername_attachment . '/');

                        if (curl_save_image($attachment_url, $foldername_attachment . $filename_attachment)) {
                            $attachment_log .= '       attachement, type:' . strtoupper($attachment_extension) . ' : ' . $filename_attachment . "\n";
                            $attachment_log .= '       saved to: ' . preg_windows_slashes($foldername_info . $filename_attachment, "\\") . "\n";
                            $attachment_log .= '       from URL: ' . $attachment_url . "\n";
                            $post_count += 1;
                        } else {
                            $attachment_log .= '--- Error while saving attachement: "' . $foldername_info . $filename_attachment . '" from url ' . $attachment_url . "\n";
                        }
                    }
                }

                if (!empty($media_attachements)) {
                    $frontmatter['media_order'] = join(', ', array_filter($media_attachements));
                }

                // Process content
                $html = str_get_html($content);
                if (empty($html)) {
                    $html = new \stdClass();
                } else {

                    // find all images
                    foreach ($html->find('img') as $e) {
                        echo '      inline image: ' . $e->src . "\n";
                        $inline_image_src = (string)$e->src;
                        $inline_image_alt = (string)$e->alt;
                        $inline_image_class = (string)$e->class;
                        $inline_image_width = (string)$e->width;
                        $inline_image_height = (string)$e->height;

                        $inline_image_options = '';

                        if ($inline_image_class != '') {
                            $inline_image_options = add_variable_operators($inline_image_options);
                            $inline_image_class = str_replace(' ', ',', $inline_image_class);
                            $inline_image_options .= 'classes=' . $inline_image_class;
                        }

                        if ($inline_image_width != '') {
                            $inline_image_options = add_variable_operators($inline_image_options);
                            $inline_image_options .= 'width=' . $inline_image_width;
                        }

                        if ($inline_image_height != '') {
                            $inline_image_options = add_variable_operators($inline_image_options);
                            $inline_image_options .= 'height=' . $inline_image_height;
                        }

                        $e->outertext = '![' . $inline_image_alt . '](' . basename($inline_image_src) . $inline_image_options . ')';

                    }

                    // links
                    foreach ($html->find('a') as $e) {
                        $inline_link_title = (string)$e->title;
                        $inline_link_href = (string)$e->href;
                        $inline_link_text = (string)$e->innertext;
                        $inline_link_class = (string)$e->class;
                        $inline_link_options = '';

                        echo '      inline link: ' . $e->href . "\n";

                        if ($inline_link_class != '') {
                            $inline_link_options = add_variable_operators($inline_link_options);
                            $inline_link_class = str_replace(' ', ',', $inline_link_class);
                            $inline_link_options .= 'class=' . $inline_link_class;
                        }

                        if ($inline_link_title != '') {
                            $inline_link_options = add_variable_operators($inline_link_options);
                            $inline_link_options .= 'title=' . $inline_link_title;
                        }

                        // if link is some attached document or archive eg. pdf, zip etc.
                        if (in_array(basename($inline_link_href), $media_attachements)) {
                            $inline_link_href = basename($inline_link_href);
                        }

                        $e->outertext = '[' . $inline_link_text . '](' . $inline_link_href . $inline_link_options . ')';
                    }
                }

                $content = $html;

                // Display content and save file
                echo '--- content: ' . "\n" . $content;
                $file->save("---\n" . Yaml::dump($frontmatter, 3) . "---\n" . $content);
                echo '    saved to: ' . preg_windows_slashes($filename, "\\") . "\n";
                echo $attachment_log;
                $post_count += 1;
            }

            //
            // save attachments who don't have parent posts
            // check if it's an attachement ---------------------------------------------------------------------------
            //
            else if ((string)$item->children('wp', true)->post_type == 'attachment' && !(string)$item->children('wp', true)->post_parent) {

                if (!file_exists(preg_windows_slashes($bloglocation . '/' . $media_folder))) {
                    if (!mkdir(preg_windows_slashes($bloglocation . '/' . $media_folder))) {
                        echo "--- Error: failed to create folder: " . preg_windows_slashes($bloglocation . '/' . $media_folder) . "\n";
                    }
                }

                $title = (string)$item->title;
                $post_id = (string)$item->children('wp', true)->post_id;
                $date = (string)$item->pubDate;
                $link = (string)$item->link;
                $slug = (string)$item->children('wp', true)->post_name;
                $post_parent = (string)$item->children('wp', true)->post_parent;
                $has_parent = false;

                foreach ($xml->channel->item as $item_parent) {
                    if ($item_parent->children('wp', true)->post_id == $post_parent) {
                        $post_parent_title = (string)$item_parent->title;
                        echo "\n--- post_id: " . $post_id . ", post_parent_id: " . $post_parent . ", post_parent_title: " . $post_parent_title . "\n";
                        $has_parent = true;
                    }
                }

                if (!$has_parent) {
                    echo "\n--- post_id: " . $post_id . ", post_parent_id: none" . "\n";
                    $post_parent_title = $title;
                }

                $attachment_url = (string)$item->children('wp', true)->attachment_url;
                $attachment_extension = strtolower(pathinfo($attachment_url, PATHINFO_EXTENSION));
                $attachment_file_name = $slug . '.' . $attachment_extension;
                $filename = $attachment_file_name;
                $foldername = preg_windows_slashes($bloglocation . '/' . $media_folder . '/' . $slug);
                $foldername_info = $foldername = str_replace('\\\\', "\\", $foldername);

                if (!file_exists($foldername)) {
                    if (!mkdir($foldername)) {
                        echo "--- Error: failed to create folder: " . $foldername_info . "\n";
                    }
                }

                $foldername = preg_windows_slashes($foldername . '/');

                if (curl_save_image($attachment_url, $foldername . $filename)) {
                    echo '--- attachement, type:' . strtoupper($attachment_extension) . ' : ' . $filename . "\n";
                    echo '    saved to: ' . $foldername_info . $filename . "\n";
                    echo '    from URL: ' . $attachment_url . "\n";
                    $post_count += 1;
                } else {
                    echo '--- Error while saving attachement: "' . $foldername_info . $filename . '" from url ' . $attachment_url . "\n";
                }
            }
        }

        $this->output->writeln("\n" . "Created " . $post_count . " posts." . "\n");
    }
}
