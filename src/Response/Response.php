<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI\Response;

use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;
use Joomla\Http\Response as HttpResponse;

/**
 * AI response data object class.
 *
 * @since  __DEPLOY_VERSION__
 */
class Response extends HttpResponse
{
    /**
     * The provider of the response.
     *
     * @var string
     * @since  __DEPLOY_VERSION__
     */
    private string $provider;

    /**
     * The metadata of the response.
     *
     * @var array
     * @since  __DEPLOY_VERSION__
     */
    private array $metadata;

    /**
     * Constructor.
     *
     * @param   string  $content   The content of the response.
     * @param   string  $provider  The provider of the response.
     * @param   array   $metadata  The metadata of the response.
     * @param   int     $status    The status code of the response.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(string $content, string $provider, array $metadata = [], int $status = 200)
    {
        parent::__construct('php://memory', $status);

        $body = $this->getBody();
        $body->write($content);
        $body->rewind();

        $this->provider = $provider;
        $this->metadata = $metadata;
    }

    /**
     * Get the content of the response.
     *
     * @return string
     * @since  __DEPLOY_VERSION__
     */
    public function getContent(): string
    {
        return (string) $this->getBody();
    }

    /**
     * Save the response content to file(s) based on response format.
     *
     * @param string $filename  The base filename.
     *
     * @throws \RuntimeException
     * @since  __DEPLOY_VERSION__
     */
    public function saveFile(string $filename)
    {
        // Create directory if it doesn't exist
        $dir = dirname($filename);
        if (!is_dir($dir)) {
            if (!Folder::create($dir)) {
                throw new \RuntimeException('Failed to create directory: ' . $dir);
            }
        }

        $metadata = $this->getMetadata();
        $format   = $metadata['response_format'] ?? null;
        $content  = $this->getContent();

        // Handle images with base64 data
        if ($format === 'b64_json') {
            $savedFiles = [];
            $imageCount = $metadata['image_count'] ?? 1;
            $dir = Path::clean(dirname($filename));
            $baseName = pathinfo($filename, PATHINFO_FILENAME);
            $ext = pathinfo($filename, PATHINFO_EXTENSION) ?: 'png';

            $data = json_decode($content, true);
            if ($imageCount === 1) {
                $decodedContent = base64_decode($content);
                if (File::write($filename, $decodedContent)) {
                    $savedFiles[] = $filename;
                }
            } elseif (is_array($data)) {
                foreach ($data as $index => $b64) {
                    $file = Path::clean($dir . '/' . $baseName . '_' . ($index + 1) . '.' . $ext);
                    $decodedContent = base64_decode($b64);
                    if (File::write($file, $decodedContent)) {
                        $savedFiles[] = $file;
                    }
                }
            }
            return $savedFiles;
        }

        // Handle images with URLs
        if ($format === 'url') {
            $imageCount = $metadata['image_count'] ?? 1;
            $data = json_decode($content, true);

            $lines = [];
            if ($imageCount === 1) {
                $lines[] = "  Image URL: " . $content;
            } elseif (is_array($data)) {
                foreach ($data as $index => $url) {
                    if (is_array($url) && isset($url['url'])) {
                        $url = $url['url'];
                    }
                    $lines[] = "  Image " . ($index + 1) . ": " . $url;
                }
            }

            if (!empty($lines)) {
                if (File::write($filename, implode(PHP_EOL, $lines))) {
                    return [$filename];
                }
            }
        }

        // For binary content (like audio files)
        if (isset($metadata['data_type']) && $metadata['data_type'] === 'binary_audio') {
            if (File::write($filename, $content)) {
                return [$filename];
            }
        }

        // For all other content
        if ($content !== '') {
            if (File::write($filename, $content)) {
                return [$filename];
            }
        }

        return false;
    }

    /**
     * Get the metadata of the response.
     *
     * @return  array  The metadata of the response.
     * @since  __DEPLOY_VERSION__
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get the provider of the response.
     *
     * @return  string  The provider of the response.
     * @since  __DEPLOY_VERSION__
     */
    public function getProvider(): string
    {
        return $this->provider;
    }
}
