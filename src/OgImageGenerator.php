<?php
namespace juekr;

use Imagick;
use ImagickPixel;

class OgImageGenerator {
    private $BASE_FOLDER = null;
    private $SITE_ROOT = null; 
    private $SITE_URL = null;
    private $cache_dir = null;
    private $save_dir_url = null;
    private $font_dir = null;
    private $save_dir = null;

    private $last_image;

    private $og_resolution_w = 1200, $og_resolution_h = 630;

    private $base_image = null;
    private $text = [], $overlay_images = [];
    private $background_color = '#B3B3B3';
    private $text_default_values = [
        "font" => "HomeVideo",
        "pos_x" => 10, "pos_y" => 10, 
        "color" => '#fff',
        "fontsize" => "120",
        "angle" => 0,
        "linelength" => 1180,
        "lineheight" => 0.9
    ];
    private $overlay_default_values = [
        "pos_x" => 0, "pos_y" => 0, 
        "resize" => 500,
        "angle" => 0
    ];

    public function __construct()
    {
        $this->setup_paths_and_directories();
        $this->set_font($this->text_default_values['font']);
    }

    /* DIRECTORIES -------------------------------- */

    private function setup_paths_and_directories() 
    {
        $root=pathinfo($_SERVER['SCRIPT_FILENAME']);
        $this->BASE_FOLDER = basename($root['dirname']);
        $this->SITE_ROOT = realpath(dirname(__FILE__).DIRECTORY_SEPARATOR.'..');
        $this->SITE_URL = empty($_SERVER['HTTP_HOST']) ? "" : 'http'. (!empty($_SERVER['HTTPS']) ? "s" : "") .'://'.$_SERVER['HTTP_HOST'].'/'.$this->BASE_FOLDER;

        $this->cache_dir = $this->set_cache_dir();
        $this->save_dir = $this->set_save_dir();
        $this->save_dir_url = $this->set_save_dir_url();
        $this->font_dir = $this->set_font_dir();
        // var_dump(array(
        //     "cache" => $this->cache_dir,
        //     "save" => $this->save_dir,
        //     "save_url" => $this->save_dir_url,
        //     "font_dir" => $this->font_dir,
        //     "font" => $this->set_font($this->text_default_values["font"])
        // ));die();
    }

    public function set_font_dir($dir = null) 
    {
        if (!empty($dir) && file_exists($dir) && is_dir($dir)):
            $this->font_dir = $dir;
        elseif(empty($dir)):
            $this->font_dir = $this->SITE_ROOT.DIRECTORY_SEPARATOR.'fonts'.DIRECTORY_SEPARATOR;
        endif;
        return $this->font_dir;
    }

    public function set_save_dir($dir = null) 
    {
        if (!empty($dir) && file_exists($dir) && is_dir($dir)):
            $this->save_dir = $dir;
        elseif (empty($dir)):
            $this->save_dir = $this->SITE_ROOT.DIRECTORY_SEPARATOR."og-images".DIRECTORY_SEPARATOR;
        endif;
        return $this->save_dir;
    }

    public function set_cache_dir($dir = null) 
    {
        if (!empty($dir) && file_exists($dir) && is_dir($dir)):
            $this->cache_dir = $dir;
        elseif (empty($dir)):
            $this->cache_dir = $this->SITE_ROOT.DIRECTORY_SEPARATOR."cache".DIRECTORY_SEPARATOR;
        endif;
        return $this->cache_dir;
    }

    public function set_save_dir_url($relative_url = null) 
    {
        if (!empty($url)):
            $this->save_dir_url = $this->SITE_URL. substr($this->SITE_URL,-1) == "/" ? "" : "/"  .(substr($relative_url,0,1) == "/" ? substr($relative_url,1) : $relative_url);
        else:
            $this->save_dir_url = $this->SITE_URL. substr($this->SITE_URL,-1) == "/" ? "" : "/" . "og-images/";
        endif;
        return $this->save_dir_url;
    }

    # 🗑️
    function getRelativePath($from, $to)
    {
        // some compatibility fixes for Windows paths
        $from = is_dir($from) ? rtrim($from, '\/') . '/' : $from;
        $to   = is_dir($to)   ? rtrim($to, '\/') . '/'   : $to;
        $from = str_replace('\\', '/', $from);
        $to   = str_replace('\\', '/', $to);

        $from     = explode('/', $from);
        $to       = explode('/', $to);
        $relPath  = $to;

        foreach($from as $depth => $dir) {
            // find first non-matching dir
            if($dir === $to[$depth]) {
                // ignore this directory
                array_shift($relPath);
            } else {
                // get number of remaining dirs to $from
                $remaining = count($from) - $depth;
                if($remaining > 1) {
                    // add traversals up to first matching dir
                    $padLength = (count($relPath) + $remaining - 1) * -1;
                    $relPath = array_pad($relPath, $padLength, '..');
                    break;
                } else {
                    $relPath[0] = './' . $relPath[0];
                }
            }
        }
        return implode('/', $relPath);
    }



    public function set_default_xy_pos($x = 0, $y = 0)
    {
        if (is_int($x)) $this->overlay_default_values["pos_x"] = $x;
        if (is_int($y)) $this->overlay_default_values["pos_y"] = $y;
        return [$this->overlay_default_values["pos_x"], $this->overlay_default_values["pos_y"]];
    }

    public function set_default_text_xy_pos($x = 0, $y = 0)
    {
        if (is_int($x)) $this->text_default_values["pos_x"] = $x;
        if (is_int($y)) $this->text_default_values["pos_y"] = $y;
        return [$this->text_default_values["pos_x"], $this->text_default_values["pos_y"]];
    }

    public function set_default_resize($size = null) 
    {
        if (empty($size) or $size == 0) $this->overlay_default_values["resize"] = false; else $this->overlay_default_values["resize"] = $size;
        return $this->overlay_default_values["resize"];
    }

    public function set_font($font = null, $size = null, $set_as_default = true) 
    {
        $_font = null;
        if (empty($font)) return $this->text_default_values["font"];
        if (file_exists($font)):
            $_font = $font;
        elseif (file_exists($font.".ttf")):
            $_font = $font.".ttf";
        elseif (file_exists($this->font_dir.$font)):
            $_font = $this->font_dir.$font;
        elseif (file_exists($this->font_dir.$font.".ttf")):
            $_font = $this->font_dir.$font.".ttf";
        else:
            $_font = $this->font_dir."Rainbow2000.ttf";
        endif;
        if (!empty($size) && $size > 0) $this->text_default_values["fontsize"] = $size;
        if ($set_as_default !== true) return $_font;
        $this->text_default_values["font"] = $_font;
        return $this->text_default_values["font"]; # , $this->text_default_values["fontsize"], $this->text_default_values["color"] ];
    }

    public function set_default_text_color($color = '#fff')
    {
        if (!empty($color)) $this->text_default_values["color"] = $color;
        return $this->text_default_values["color"];
    }

    public function set_background_color($color = '#fff')
    {
        if (!empty($color)) $this->background_color = $color;
        return $this->background_color;
    }

    public function add_text($str = "", $opt = []) 
    {
        if (empty($str)) return $this->text;
        $this->text[] = [ $str, $this->fill_text_defaults($opt) ];
        return $this->text;
    }

    public function add_overlay_image($image_path = "", $opt = []) 
    {
        if (file_exists($image_path)):
            $this->overlay_images[] = [ $image_path, $this->fill_overlay_defaults($opt) ];
        endif;
        return $this->overlay_images;
    }

    public function get_og_image_url($absolute_path = null) 
    {
        if (empty($absolute_path)) $absolute_path = $this->last_image;
        if (empty($absolute_path) || !file_exists($absolute_path)) return null;
        $filename = basename($absolute_path);
        return $this->save_dir_url . (substr($this->save_dir_url,-1) == "/" ? "" : "/") .$filename;
    }

    public function make_og_image($force_fresh = false)
    {
        if (TRUE !== extension_loaded('imagick')):
            $this->error('ERR: no imagick!', true);
        endif;
        $hash = md5(serialize($this->overlay_images) . serialize($this->text));
        $save_path = $this->save_dir . "" . $hash . ".png";
        $this->last_image = $save_path;

        if (file_exists($save_path) && $force_fresh === false) return $this->get_og_image_url($save_path);

        $top_image = new Imagick();
        $base_image = new Imagick();

        // if (FALSE === $base_image->readImage($this->base_image)):
        //     $this->error('ERR: error readin base image at '.$base_image, true);
        // endif;
        $base_image->newImage(
                $this->og_resolution_w,
                $this->og_resolution_h, 
                new ImagickPixel($this->background_color), 
                'png'
        );
        $base_image->setImageFileName($save_path);

        if ($this->overlay_images && count($this->overlay_images) > 0) :
            foreach ($this->overlay_images as $individual_image):
                if (FALSE === $top_image->readImage($individual_image[0])): 
                    $this->error("ERR: error reading individual image at: ".$individual_image[0]);
                    continue;
                endif;
                if (is_int($individual_image[1]["resize"]) && $individual_image[1]["resize"] <= 0):
                    $this->error("ERR: cannot resize image to values <= 0 (".$individual_image[1]["resize"].", ".$individual_image[0].")");
                    continue;
                endif;

                list($source_image_width, $source_image_height, $source_image_type) = getimagesize($individual_image[0]);
                $source_aspect_ratio = $source_image_width / $source_image_height;
                if ($source_image_width <= $source_image_height) {
                    $thumbnail_image_width = (int) ($individual_image[1]["resize"] * $source_aspect_ratio);
                    $thumbnail_image_height = $individual_image[1]["resize"];
                } else {
                    $thumbnail_image_width = $individual_image[1]["resize"];
                    $thumbnail_image_height = (int) ($individual_image[1]["resize"] / $source_aspect_ratio);
                }

                $top_image->thumbnailImage($thumbnail_image_width, $thumbnail_image_height, TRUE);
                if ($individual_image[1]["angle"] != 0):
                    $top_image->rotateimage(new \ImagickPixel('#00000000'), $individual_image[1]["angle"]);
                endif;

                $base_image->compositeImage($top_image, \Imagick::COMPOSITE_DEFAULT, $individual_image[1]["pos_x"], $individual_image[1]["pos_y"]);
            endforeach;
        endif;

        if ($this->text && count($this->text) > 0) :
            foreach ($this->text as $text_snippet):
                $draw = new \ImagickDraw();
                $draw->setFillColor($text_snippet[1]["color"]);
                $draw->setFont($this->set_font($text_snippet[1]["font"] ?? "", $text_snippet[1]["fontsize"] ?? 12, true));
                $draw->setFontSize($text_snippet[1]["fontsize"] ?? 12);

                // calculate space for lines and write line by line
                list($lines, $lineHeight) = $this->wordWrapAnnotation($base_image, $draw, $text_snippet[0], $text_snippet[1]["linelength"]);
                for($i = 0; $i < count($lines); $i++):
                    $base_image->annotateImage($draw, $text_snippet[1]["pos_x"], $text_snippet[1]["pos_y"] + $text_snippet[1]["fontsize"] + $i * ($text_snippet[1]["lineheight"] * $lineHeight), 0, $lines[$i]);
                endfor;
            endforeach;
        endif;

        if (FALSE == $base_image->writeImage()):
            $this->error($error = 'ERR: error while writing to: ' . $save_path, $die = true);
        endif;
        return $this->get_og_image_url($save_path);
    }

    private function fill_text_defaults($arr) 
    {
        return $this->fill_defaults("text_default_values", $arr);
    }

    private function fill_overlay_defaults($arr) 
    {
        return $this->fill_defaults("overlay_default_values", $arr);
    }

    private function fill_defaults($which, $arr) 
    {
        foreach ($this->$which as $key => $val):
            if (empty($arr[$key])): 
                if($key == "font"):
                    $arr[$key] = $this->set_font($val, null, true);
                else:
                    $arr[$key] = $val;
                endif;
            endif;
        endforeach;
        return $arr;
    }

    private function error(string $error = "", bool $die = true):void 
    {
        echo $error."\n";
        if ($die === true) die();
    }

    /* Implement word wrapping... Ughhh... why is this NOT done for me!!!
        OK... I know the algorithm sucks at efficiency, but it's for short messages, okay?

        Make sure to set the font on the ImagickDraw Object first!
        @param image the Imagick Image Object
        @param draw the ImagickDraw Object
        @param text the text you want to wrap
        @param maxWidth the maximum width in pixels for your wrapped "virtual" text box
        @return an array of lines and line heights

        usage:
        <?php
        list($lines, $lineHeight) = wordWrapAnnotation($image, $draw, $msg, 140);
        for($i = 0; $i < count($lines); $i++)
            $image->annotateImage($draw, $xpos, $ypos + $i*$lineHeight, 0, $lines[$i]);
        ?>
    */
    # source: https://stackoverflow.com/questions/5746537/how-can-i-wrap-text-using-imagick-in-php-so-that-it-is-drawn-as-multiline-text
    private function wordWrapAnnotation($image, $draw, $text, $maxWidth)
    {   
        $text = trim($text);

        $words = preg_split('%\s%', $text, -1, PREG_SPLIT_NO_EMPTY);
        $lines = array();
        $i = 0;
        $lineHeight = 0;

        while (count($words) > 0)
        {   
            $metrics = $image->queryFontMetrics($draw, implode(' ', array_slice($words, 0, ++$i)));
            $lineHeight = max($metrics['textHeight'], $lineHeight);

            // check if we have found the word that exceeds the line width
            if ($metrics['textWidth'] > $maxWidth or count($words) < $i) 
            {   
                // handle case where a single word is longer than the allowed line width (just add this as a word on its own line?)
                if ($i == 1)
                    $i++;

                $lines[] = implode(' ', array_slice($words, 0, --$i));
                $words = array_slice($words, $i);
                $i = 0;
            }   
        }   

        return array($lines, $lineHeight);
    }

}
