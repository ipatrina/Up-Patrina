# Up Patrina

Up Patrina is a standalone PHP script for rapid file uploads. Clients upload large files in single-threaded chunks with standard HTML5 browsers.

![Up Patrina preview](https://thumbs2.imgbox.com/28/a8/i2iX34tk_t.png)

# New Features added:

* Drag and drop files directly into the browser to upload.
* Improved browser compatibility. Supports Chrome 48, Firefox 47, IE 11 and later versions. (Drag and drop uploading is not supported by IE 11.)
* Fixed recurring HTTP 409 errors during uploads and optimized the upload workflow for more consistent performance across browsers.
* Responsive design for a better experience on mobile devices.
* Automatic language detection: Chinese is displayed by default for Chinese systems or browsers; otherwise, English is used.
* File list headers are automatically displayed when files are present.
* Clicking an uploaded file downloads it directly instead of opening it in the browser.
* Improved delete button styling.

# Operate environment

- PHP 8 and above.

# Configurations

Nothing. Just works.

Its authentication strategy is that only authorized users are privy to the name of the PHP file - for instance, `mySecretUploadUri.php`. Files will be uploaded to the same directory.
