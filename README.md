# File Downloader

A professional PHP library for downloading files with support for HTTP, HTTPS, FTP, local files, and parallel downloads.

## Features

- ✅ Multi-protocol support (HTTP, HTTPS, FTP, local files)
- ✅ Chunked streaming for large file downloads
- ✅ Asynchronous and parallel downloads
- ✅ Built-in image and file type processing
- ✅ Validation system (size, MIME type, extension)
- ✅ Guzzle HTTP Client integration
- ✅ Extensible architecture
- ✅ PHP 8.0+

## Installation

```bash
composer require coderden/file-downloader
```

## Quick Start

### Simple File Download

```php
// Initialization (optional)
FileDownloadHelper::init([
    'default_destination' => __DIR__ . '/downloads',
    'timeout' => 60,
]);

// Download single file
$result = FileDownloadHelper::download('https://example.com/image.jpg');

// Quick download with auto-generated filename
$filePath = FileDownloadHelper::quickDownload('https://example.com/file.pdf');

// Download multiple files
$results = FileDownloadHelper::downloadMultiple([
    'https://example.com/file1.jpg',
    'https://example.com/file2.png',
    'ftp://user:pass@example.com/file3.zip',
]);
```

### Using Builder Pattern

```php
use CodeDen\FileDownloader\DownloadBuilder;

$result = (new DownloadBuilder())
    ->from('https://example.com/large-file.zip')
    ->to('/path/to/downloads')
    ->chunked(true)
    ->chunkSize(2 * 1024 * 1024) // 2MB chunks
    ->maxSize(500 * 1024 * 1024) // 500MB max
    ->download();
```

## Core Features

### Multi-Protocol Support

```php
// HTTP/HTTPS
FileDownloadHelper::download('https://example.com/file.jpg');

// FTP
FileDownloadHelper::downloadViaFtp(
    'ftp://username:password@example.com/path/to/file.zip',
    __DIR__ . '/downloads/file.zip',
    [
        'port' => 21,
        'timeout' => 30,
    ]
);

// Local files (copying)
FileDownloadHelper::download('file:///path/to/local/file.txt');
```

### Large File Downloads

```php
// Chunked streaming download
$result = FileDownloadHelper::downloadLargeFile(
    'https://example.com/ubuntu-22.04.iso',
    __DIR__ . '/downloads/ubuntu.iso',
    5 * 1024 * 1024, // 5MB chunks
    function ($downloaded, $total) {
        $percent = $total > 0 ? ($downloaded / $total) * 100 : 0;
        echo "Downloaded: " . round($percent, 2) . "%\n";
    }
);
```

### Parallel Downloads

```php
// Asynchronous multiple file download
$results = FileDownloadHelper::downloadParallel(
    [
        'https://example.com/file1.jpg',
        'https://example.com/file2.jpg',
        'https://example.com/file3.jpg',
    ],
    __DIR__ . '/downloads',
    3 // 3 concurrent downloads
);

// Download with rate limiting
$results = FileDownloadHelper::downloadWithRateLimit(
    $urls,
    __DIR__ . '/downloads',
    2 // 2 requests per second
);
```

### File Validation

```php
$manager = new FileDownloadManager();
$manager->addValidator(new SizeValidator(10 * 1024 * 1024)); // 10MB max
$manager->addValidator(new MimeTypeValidator(['image/jpeg', 'image/png']));

$result = (new DownloadBuilder($manager))
    ->from('https://example.com/image.jpg')
    ->to(__DIR__ . '/downloads')
    ->download();
```

## Advanced Usage

### Custom Downloader

```php
class S3Downloader implements DownloaderInterface
{
    public function download(string $source, string $destination): bool
    {
        // Implement S3 download logic
        return true;
    }

    public function supports(string $protocol): bool
    {
        return str_starts_with($protocol, 's3://');
    }

    public function setOptions(array $options): void
    {
        // Configure options
    }
}

// Usage
$manager = new FileDownloadManager();
$manager->addDownloader(new S3Downloader());
```

### Custom Validator

```php
use CodeDen\FileDownloader\Contracts\ValidatorInterface;

class VirusScannerValidator implements ValidatorInterface
{
    public function validate(string $filePath): bool
    {
        // Scan file for viruses
        return $this->scanForViruses($filePath);
    }

    public function getErrorMessage(): string
    {
        return 'File contains viruses';
    }
}
```

## Configuration

### Global Configuration

```php
// config/downloader.php
return [
    'defaults' => [
        'destination' => storage_path('downloads'),
        'timeout' => 60,
        'chunk_size' => 1048576, // 1MB
        'max_file_size' => 1073741824, // 1GB
        'allowed_extensions' => [
            'jpg', 'jpeg', 'png', 'gif', 'pdf', 'zip', 'txt'
        ],
    ],
    'ftp' => [
        'default_port' => 21,
        'timeout' => 30,
        'passive_mode' => true,
    ],
    'http' => [
        'timeout' => 30,
        'connect_timeout' => 10,
        'verify_ssl' => true,
        'follow_redirects' => true,
        'headers' => [
            'User-Agent' => 'CodeDen-FileDownloader/1.0',
        ],
    ],
    'events' => [
        'enabled' => true,
        'progress_interval' => 5, // seconds
    ],
    'retry' => [
        'max_attempts' => 3,
        'delay' => 1000, // milliseconds
    ]
];
```

### Initialization with Configuration

```php
FileDownloadHelper::init(include 'config/downloader.php');
```

### DownloadBuilder (Fluent Interface)

```php
$builder = new DownloadBuilder()
    ->from($url)                    // Source (string or array)
    ->to($destination)              // Destination directory
    ->withOptions($options)         // Options
    ->chunked(true)                 // Enable chunked download
    ->chunkSize($bytes)             // Chunk size
    ->maxSize($bytes)               // Maximum file size
    ->timeout($seconds)             // Timeout
    ->download();                   // Execute download
```

### FileDownloadManager

Main class for managing downloads with custom component support.

```php
$manager = new FileDownloadManager();
$manager->addDownloader(new CustomDownloader());
$manager->addValidator(new CustomValidator());
```

## Error Handling

```php
try {
    $result = FileDownloadHelper::download('https://example.com/file.jpg');
} catch (DownloadFailedException $e) {
    echo "Download error: " . $e->getMessage();
} catch (ValidationFailedException $e) {
    echo "Validation error: " . $e->getMessage();
}
```

## Examples

### Bulk Download with Limits

```php
// Download 100 files with 5 concurrent limit
$urls = [/* array of 100 URLs */];

$results = FileDownloadHelper::batchDownload(
    $urls,
    __DIR__ . '/downloads/gallery',
    5, // concurrency
    function ($completed, $total) {
        echo "Completed: {$completed}/{$total}\n";
    }
);
```

### Framework Integration

```php
// Laravel Service Provider
namespace App\Providers;

use CodeDen\FileDownloader\FileDownloadManager;
use Illuminate\Support\ServiceProvider;

class FileDownloadServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(FileDownloadManager::class, function ($app) {
            $manager = new FileDownloadManager();
            
            // Configuration from config/filesystems.php
            $config = $app['config']['filesystems.disks'];
            
            // Add custom downloaders
            foreach ($config['downloaders'] ?? [] as $downloader) {
                $manager->addDownloader(new $downloader());
            }
            
            return $manager;
        });
    }
}
```
