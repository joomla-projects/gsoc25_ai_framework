<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI\Response;

/**
 * AI response data object class.
 *
 * @since  __DEPLOY_VERSION__
 */
class Response
 {
    /**
     * The content of the response.
     *
     * @var string
     * @since  __DEPLOY_VERSION__
     */
    private $content;

    /**
     * The status code of the response.
     *
     * @var int
     * @since  __DEPLOY_VERSION__
     */
    private $statusCode;

    /**
     * The metadata of the response.
     *
     * @var array
     * @since  __DEPLOY_VERSION__
     */
    private $metadata;

    /**
     * The provider of the response.
     *
     * @var string
     * @since  __DEPLOY_VERSION__
     */
    private $provider;

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
        $this->content = $content;
        $this->provider = $provider;
        $this->metadata = $metadata;
        $this->statusCode = $status;
    }

    /**
     * Get the content of the response.
     *
     * @return  string  The content of the response.
     * @since  __DEPLOY_VERSION__
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Save the response content to file(s) based on response format.
     *
     * @param string $filename  The base filename.
     */
    public function saveContentToFile(string $filename)
    {
        $metadata = $this->getMetadata();
        $format   = $metadata['response_format'] ?? null;
        $content  = $this->getContent();

        // Handle images with base64 data
        if ($format === 'b64_json') {
            $savedFiles = [];
            $imageCount = $metadata['image_count'] ?? 1;
            $dir = pathinfo($filename, PATHINFO_DIRNAME);
            $baseName   = pathinfo($filename, PATHINFO_FILENAME);
            $ext        = pathinfo($filename, PATHINFO_EXTENSION) ?: 'png';

            $data = json_decode($content, true);
            if ($imageCount === 1 && is_string($content)) {
                file_put_contents($filename, base64_decode($content));
                $savedFiles[] = $filename;
            } elseif (is_array($data)) {
                foreach ($data as $index => $b64) {
                    $file = ($dir !== '.' ? $dir . DIRECTORY_SEPARATOR : '') . $baseName . '_' . ($index + 1) . '.' . $ext;
                    file_put_contents($file, base64_decode($b64));
                    $savedFiles[] = $file;
                }
            }
            return $savedFiles;
        }

        // Handle images with URLs
        if ($format === 'url') {
            $imageCount = $metadata['image_count'] ?? 1;
            $data = json_decode($content, true);

            $lines = [];
            if ($imageCount === 1 && is_string($content)) {
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
                file_put_contents($filename, implode(PHP_EOL, $lines));
                return [$filename];
            }
        }

        // For all other content
        if ($content !== null) {
            file_put_contents($filename, $content);
            return [$filename];
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

    /**
     * Get the status code of the response.
     *
     * @return  int  The status code of the response.
     * @since  __DEPLOY_VERSION__
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Magic method to access properties of the response object.
     *
     * @param   string  $name  The name of the property to get.
     *
     * @return  mixed  The value of the property.
     * @since  __DEPLOY_VERSION__
     */
    public function __get($name)
    {
        switch (strtolower($name)) {
            case 'content':
                return $this->getContent();

            case 'metadata':
                return $this->getMetadata();

            case 'provider':
                return $this->getProvider();

            case 'statuscode':
                return $this->getStatusCode();

            default:
                $trace = debug_backtrace();

                trigger_error(
                    sprintf(
                        'Undefined property via __get(): %s in %s on line %s',
                        $name,
                        $trace[0]['file'],
                        $trace[0]['line']
                    ),
                    E_USER_NOTICE
                );

                break;
        }
    }
}
