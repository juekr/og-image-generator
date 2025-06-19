<?php declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';

use juekr\OgImageGenerator;
use PHPUnit\Framework\TestCase;

final class ImagickTest extends TestCase
{
    public function test_paths():void 
    {
        
    }
    public function test_setup():void
    {
        $class = new OgImageGenerator();
        ;
        $this->assertIsString($class->make_og_image(true));

        #$class->set_cover(__DIR__."/test.jpg");
        $class->set_base_image(__DIR__.'/../static/assets/og_episode_base.png');
        $class->set_xy_pos(100,10);
        $class->set_text_xy_pos(80,20);
        $class->set_resize(500);
        $class->set_color("#f18");
        
        var_dump($class->add_overlay_image(__DIR__."/test.jpg", [
            "pos_x" => 160, // 860,
            "pos_y" => 250,
            "resize" => 290,
            "angle" => -3
        ]));

        $class->add_text("[PODCAST-EPISODE]", [
            "font" => "Arial Black",
            "fontsize" => "60",
            "pos_x" => 490,
            "pos_y" => 325,
            "color" => "#E5E059",
            "linelength" => 600,
            "lineheight" => 0.8
        ]);
        $class->add_text("Krankenhausaufenthalte – was passiert da so? (live aus dem Afterwork) [S2E7]", [
            "font" => "Arial",
            "fontsize" => "64",
            "pos_x" => 490,
            "pos_y" => 390,
            "color" => "#FFFFFF",
            "linelength" => 600,
            "lineheight" => 0.8
        ]);

        $file = $class->make_og_image(true);
        $this->assertIsString($file);

            exec("code \"".$file."\" && sleep 6 && rm \"$file\"");
    }
}

?>