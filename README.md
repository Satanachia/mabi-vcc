# Mabinogi Visual Chat Converter
A converter for images to Mabinogi visual chat format written in PHP
 
## Requirements
You will need PHP and the Command-line binary for [pngquant](https://pngquant.org). 

## Usage
POST to mabivcc.php with two images using mabiVc and newImage.
Example from demo
```
<form action="mabivcc.php" method="post" enctype="multipart/form-data">
    Existing Visual Chat Image: <br />
    <input type="file" id="mabiVc" name="mabiVc" accept="image/*"><br />
    Image to convert: <br />
    <input type="file" id="newImage" name="newImage" accept="image/*"><br />
    <input type="submit" value="Submit" name="submit">
</form>
```
## Preview from in game
![Preview](image.png)
