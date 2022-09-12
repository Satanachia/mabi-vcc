<?php

/**
 * Optimizes PNG file with pngquant 1.8 or later (reduces file size of 24-bit/32-bit PNG images).
 *
 * You need to install pngquant 1.8 on the server (ancient version 1.0 won't work).
 * There's package for Debian/Ubuntu and RPM for other distributions on http://pngquant.org
 *
 * @param $pathToNewVC string - path to any PNG file, e.g. $_FILE['file']['tmp_name']
 * @param $newVcExtension string - image type,  e.g. $_FILE['file']['type']
 *
 * @return string - raw content of PNG file after conversion
 */
function compressPng($pathToNewVC, $newVcExtension)
{
    if (!file_exists($pathToNewVC)) {
	header("Location: /");
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
	    header("Location: /");
            throw new Exception("File type not supported: $newVcExtension");
    }

    // Set new image size to maximum allowed by Visual Chat
    list($width, $height) = getimagesize($pathToNewVC);
    $newwidth = 256;
    $newheight = 96;

    // Prepare
    $thumb = imagecreatetruecolor($newwidth, $newheight);
    imagealphablending($thumb, false);
    imagesavealpha($thumb, true); 

    // Resize
    imagecopyresized($thumb, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);

    // Output
    imagepng($thumb, "/tmp/tmp.png");

    // '-' makes it use stdout, required to save to $compressed_png_content variable
    // '<' makes it read from the given file path
    $compressed_png_content = shell_exec("pngquant --speed 10 4 - < /tmp/tmp.png");
    
    if (!$compressed_png_content) {
        throw new Exception("Conversion to compressed PNG failed. Is pngquant 1.8+ installed on the server?");
    }
    
    return $compressed_png_content;
}

/**
 * Swaps PNG chunks (raw data) from $newVC into $mabiVC to convert $newVC to Visual Chat format
 *
 * $mabiVC is used as the base because $newVC can have a variety of chunks that disrupt the format
 *
 * @param $newVC string - Compressed raw PNG image to be converted
 * @param $mabiVC string - Existing raw Visual Chat PNG image
 *
 * @return string - Raw PNG file after swapping PNG chunks
 */
function swapChunks($mabiVC, $newVC){
    // Prepare new image for conversion
    $contents = $newVC;
    $pos = 8; // skip header
    
    $len = strlen($contents);
    $safety = 1000;
    // Grab chunks from the PNG being converted 
    // PLTE = Pallet; IDAT = image data; IHDR = image header
    do {
        list($unused,$chunk_len) = unpack('N', substr($contents,$pos,4));

        $chunk_type = substr($contents,$pos+4,4);

        $chunk_data = substr($contents,$pos,$chunk_len+12);

        list($unused,$chunk_crc) = unpack('N', substr($contents,$pos+8+$chunk_len,4));
        switch($chunk_type) {
            case 'IHDR':
                $IHDR = $chunk_data;
                $IHDR_chunk_len = $chunk_len;
            break;
            
            case 'PLTE':
                $PLTE = $chunk_data;
                $PLTE_chunk_len = $chunk_len;
            break;

            case 'IDAT':
                $IDAT = $chunk_data;
                $IDAT_chunk_len = $chunk_len;
            break;
            default
                break;


        }
        $pos += $chunk_len + 12;
    } while(($pos < $len) && --$safety);

    // Prepare existing image for conversion
    $contents = $mabiVC;
    $pos = 8; // skip header
	
    $len = strlen($contents);
    $safety = 1000;
    // Set chunks from the PNG being converted to existing Visual Chat PNG ($mabiVC)
    // PLTE = Pallet; IDAT = image data; IHDR = image header
    do {
        list($unused,$chunk_len) = unpack('N', substr($contents,$pos,4));

        $chunk_type = substr($contents,$pos+4,4);

        $chunk_data = substr($contents,$pos,$chunk_len);

        list($unused,$chunk_crc) = unpack('N', substr($contents,$pos+8+$chunk_len,4));
	    
	// Check for necessary chunk types and swap
        switch($chunk_type) {
            case 'IHDR':
                $contents = substr($contents,0,$pos) . $IHDR . substr($contents,$pos+12+$chunk_len);
                $chunk_len = $IHDR_chunk_len;
            break;

            case 'PLTE':
                $contents = substr($contents,0,$pos) . $PLTE . substr($contents,$pos+12+$chunk_len);
                $chunk_len = $PLTE_chunk_len;
            break;

            case 'IDAT':
                $contents = substr($contents,0,$pos) . $IDAT . substr($contents,$pos+12+$chunk_len);
                $chunk_len = $IDAT_chunk_len;
            break;

            default:
                break;

        }
        $pos += $chunk_len + 12;
    } while(($pos < $len) && --$safety);
	
    return $contents;
}

/**
 * Convert new PNG to Visual Chat format
 *
 * @param $pathToNewVC string - path to any PNG file, e.g. $_FILE['file']['tmp_name']
 * @param $newVcExtension string - image type,  e.g. $_FILE['file']['type']
 * @param $mabiVC string - path to any PNG file, e.g. $_FILE['file']['tmp_name']
 *
 * @return string - raw content of PNG file after conversion
 */
function convertPng($pathToNewVC, $newVcExtension, $mabiVC){
    // Compress new PNG
    $new_png_content = compressPng($pathToNewVC, $newVcExtension, $mabiVC);
	
    // Use default image if a file is not provided; Sets author to "Download" in game
    if (!file_exists($mabiVC)) {
        $mabiVC="temporary.png";
    }
    // Load the existing Visual Chat image
    $existingVC = file_get_contents($mabiVC);
	
    // Convert new PNG to Visual Chat format
    $new_png_content = swapChunks($existingVC, $new_png_content);
	
    return $new_png_content;
}

if(isset($_POST['submit']))
{
    // Convert PNG using provided files
    $new_png_content = convertPng($_FILES['newImage']["tmp_name"],$_FILES['newImage']["type"],$_FILES['mabiVc']["tmp_name"]);
	
    // Prepare PNG for download and set file name to usable format
    header( 'Content-type: image/png' );
    header('Content-Disposition: attachment; filename="chat_'.date('Ymd_His').'_Download.png"');
	
    // Download converted PNG
    echo $new_png_content;

}
else
{
    header("Location: /");
    exit();
}
?>
