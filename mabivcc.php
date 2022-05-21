<?php

/**
 * Optimizes PNG file with pngquant 1.8 or later (reduces file size of 24-bit/32-bit PNG images).
 *
 * You need to install pngquant 1.8 on the server (ancient version 1.0 won't work).
 * There's package for Debian/Ubuntu and RPM for other distributions on http://pngquant.org
 *
 * @param $pathToNewVC string - path to any PNG file, e.g. $_FILE['file']['tmp_name']
 * @param $newVcExtension string - image type,  e.g. $_FILE['file']['type']
 * @param $mabiVC string - path to any PNG file, e.g. $_FILE['file']['tmp_name']
 * @return string - content of PNG file after conversion
 */
function convertPng($pathToNewVC, $newVcExtension, $mabiVC)
{
    if (!file_exists($pathToNewVC)) {
        throw new Exception("File does not exist: $pathToNewVC");
    }

    switch( $newVcExtension ) {
        case "image/gif": 
            $source = imagecreatefromgif($pathToNewVC); 
            break;
        case "image/png": 
            $source = imagecreatefrompng($pathToNewVC); 
            break;
        case "image/jpeg": 
            $source = imagecreatefromjpeg($pathToNewVC); 
            break;
        default:
            throw new Exception("File type not supported: $newVcExtension");
    }

    list($width, $height) = getimagesize($pathToNewVC);
    $newwidth = 256;
    $newheight = 96;

    // Load
    $thumb = imagecreatetruecolor($newwidth, $newheight);
    imagealphablending($thumb, false);
    imagesavealpha($thumb, true); 

    // Resize
    imagecopyresized($thumb, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);

    // Output
    imagepng($thumb, "/tmp/tmp.png");

    // '-' makes it use stdout, required to save to $compressed_png_content variable
    // '<' makes it read from the given file path
    $compressed_png_content = shell_exec("pngquant 4 - < /tmp/tmp.png");
    
    if (!$compressed_png_content) {
        throw new Exception("Conversion to compressed PNG failed. Is pngquant 1.8+ installed on the server?");
    }
    

    $compressed_png_content = swapChunks($mabiVC, $compressed_png_content);
    
    //file_put_contents("shrek.png", $compressed_png_content);
    header( 'Content-type: image/png' );
    header('Content-Disposition: attachment; filename="chat_'.date('Ymd_His').'_Download.png"');
    echo $compressed_png_content;
    return $compressed_png_content;
}

function swapChunks($mabiVC, $newVC){
    $contents = $newVC;
    $pos = 8; // skip header
    
    $color_types = array('Greyscale','unknown','Truecolour','Indexed-color','Greyscale with alpha','unknown','Truecolor with alpha');
    $len = strlen($contents);
    $safety = 1000;
    do {
        list($unused,$chunk_len) = unpack('N', substr($contents,$pos,4));

        $chunk_type = substr($contents,$pos+4,4);

        $chunk_data = substr($contents,$pos,$chunk_len+12);

        list($unused,$chunk_crc) = unpack('N', substr($contents,$pos+8+$chunk_len,4));
        //echo "chunk length:$chunk_len(dec) 0x" . sprintf('%08x',$chunk_len) . "h<br>\n";
        //echo "chunk crc   :0x" . sprintf('%08x',$chunk_crc) . "h<br>\n";
        //echo "chunk type  :$chunk_type<br>\n";
        //echo "chunk data  $chunk_type bytes:<br>\n"  . chunk_split(bin2hex($chunk_data)) . "<br>\n";
        switch($chunk_type) {
            case 'IHDR':
                //echo bin2hex($chunk_data) . "\n";
                //echo $chunk_len . "\n";
                $IHDR = $chunk_data;
                $IHDR_chunk_len = $chunk_len;
            break;
            
            case 'PLTE':
                $PLTE = $chunk_data;
                $PLTE_chunk_len = $chunk_len;
                //echo "got PLTE";
            break;

            case 'IDAT':
                $IDAT = $chunk_data;
                $IDAT_chunk_len = $chunk_len;
                //echo $IDAT;
            break;
            default:
                //echo $chunk_type . "\n";
                break;


        }
        $pos += $chunk_len + 12;
        //echo "<hr>";
    } while(($pos < $len) && --$safety);

    $contents = file_get_contents($mabiVC);
    //echo bin2hex($contents) . "\n";
    $pos = 8; // skip header

    $color_types = array('Greyscale','unknown','Truecolour','Indexed-color','Greyscale with alpha','unknown','Truecolor with alpha');
    $len = strlen($contents);
    $safety = 1000;
    do {
        list($unused,$chunk_len) = unpack('N', substr($contents,$pos,4));

        $chunk_type = substr($contents,$pos+4,4);

        $chunk_data = substr($contents,$pos,$chunk_len);

        list($unused,$chunk_crc) = unpack('N', substr($contents,$pos+8+$chunk_len,4));
        //echo "chunk length:$chunk_len(dec) 0x" . sprintf('%08x',$chunk_len) . "h<br>\n";
        //echo "chunk crc   :0x" . sprintf('%08x',$chunk_crc) . "h<br>\n";
        //echo "chunk type  :$chunk_type<br>\n";
        //echo "chunk data  $chunk_type bytes:<br>\n"  . chunk_split(bin2hex($chunk_data)) . "<br>\n";
        switch($chunk_type) {
            case 'IHDR':
                //echo strlen($contents) . "\n";
                //echo 'IHDR FOUND' . $chunk_len . " " . $IHDR_chunk_len . "\n";
                $contents = substr($contents,0,$pos) . $IHDR . substr($contents,$pos+12+$chunk_len);
                //echo strlen($contents) . "\n";
                $chunk_len = $IHDR_chunk_len;
            break;

            case 'PLTE':
                //echo strlen($contents) . "\n";
                //echo 'PLTE FOUND' . $chunk_len . " " . $PLTE_chunk_len . "\n";
                $contents = substr($contents,0,$pos) . $PLTE . substr($contents,$pos+12+$chunk_len);
                //echo strlen($contents) . "\n";
                $chunk_len = $PLTE_chunk_len;
            break;

            case 'IDAT':
                //echo strlen($contents) . "\n";
                //echo 'IDAT FOUND' . $chunk_len . " " . $IDAT_chunk_len . "\n";
                $contents = substr($contents,0,$pos) . $IDAT . substr($contents,$pos+12+$chunk_len);
                //echo strlen($contents) . "\n";
                $chunk_len = $IDAT_chunk_len;
            break;

            default:
                //echo $chunk_type . "\n";
                break;

        }
        $pos += $chunk_len + 12;
        //echo "<hr>";
    } while(($pos < $len) && --$safety);
    //echo bin2hex($contents) . "\n";
    //file_put_contents("shrek2.png", $contents);
    return $contents;
}


if(isset($_POST['submit']))
{
    convertPng($_FILES['newImage']["tmp_name"],$_FILES['newImage']["type"],$_FILES['mabiVc']["tmp_name"]);
} 
?>
