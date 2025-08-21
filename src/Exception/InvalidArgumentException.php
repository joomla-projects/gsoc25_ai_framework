<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI\Exception;

/**
 * Exception thrown when invalid arguments are provided to AI methods.
 *
 * @since  __DEPLOY_VERSION__
 */
class InvalidArgumentException extends AIException
{
    /**
     * Create exception for invalid model name.
     *
     * @param   string $model          The invalid model name
     * @param   string $provider       The provider name
     * @param   array  $validModels    Array of valid models for this capability
     * @param   string $capability     The capability being used (optional)
     *
     * @return  self
     * @since  __DEPLOY_VERSION__
     */
    public static function invalidModel(string $model, string $provider, array $validModels = [], string $capability = ''): self
    {
        $message = "Model '{$model}' is not supported by {$provider}";
        
        if ($capability) {
            $message = "Model '{$model}' does not support {$capability} capability on {$provider}";
        }
        
        if (!empty($validModels)) {
            $message .= ". Valid models: " . implode(', ', $validModels);
        }
        
        return new self(
            $message,
            $provider,
            [
                'requested_model' => $model,
                'valid_models' => $validModels,
                'capability' => $capability,
                'validation_type' => 'model'
            ],
            null,
            null,
            null
        );
    }

    /**
     * Create exception for invalid temperature value.
     *
     * @param   float  $temperature  The invalid temperature value
     * @param   string $provider     The provider name
     * @param   float  $min          Minimum allowed value
     * @param   float  $max          Maximum allowed value
     *
     * @return  self
     * @since  __DEPLOY_VERSION__
     */
    public static function invalidTemperature(float $temperature, string $provider, float $min = 0.0, float $max = 2.0): self
    {
        $message = "Temperature value {$temperature} is invalid. Must be between {$min} and {$max}";
        
        return new self(
            $message,
            $provider,
            [
                'temperature' => $temperature,
                'min_value' => $min,
                'max_value' => $max,
                'validation_type' => 'temperature'
            ],
            null,
            null,
            null
        );
    }

    /**
     * Create exception for file size validation.
     *
     * @param   string $filePath    The file path
     * @param   int    $fileSize    Current file size in bytes
     * @param   int    $maxSize     Maximum allowed size in bytes
     * @param   string $provider    The provider name
     *
     * @return  self
     * @since  __DEPLOY_VERSION__
     */
    public static function fileSizeExceeded(string $filePath, int $fileSize, int $maxSize, string $provider, string $model = ''): self
    {
        $fileSizeMB = round($fileSize / 1024 / 1024, 2);
        $maxSizeMB = round($maxSize / 1024 / 1024, 2);
        
        $message = "File '{$filePath}' size ({$fileSizeMB}MB) exceeds maximum allowed size ({$maxSizeMB}MB)";
        
        if ($model) {
            $message .= " for model '{$model}'";
        }
        return new self(
            $message,
            $provider,
            [
                'file_path' => $filePath,
                'file_size_bytes' => $fileSize,
                'file_size_mb' => $fileSizeMB,
                'max_size_mb' => $maxSizeMB,
                'validation_type' => 'file_size'
            ],
            null,
            null,
            null
        );
    }

    /**
     * Create exception for invalid file format.
     *
     * @param   string $filePath        The file path
     * @param   string $currentFormat   Current file format/extension
     * @param   array  $allowedFormats  Array of allowed formats
     * @param   string $provider        The provider name
     * @param   string $operation       The operation being performed
     *
     * @return  self
     * @since  __DEPLOY_VERSION__
     */
    public static function invalidFileFormat(string $filePath, string $currentFormat, array $allowedFormats, string $provider, string $operation = ''): self
    {
        $message = "File '{$filePath}' has unsupported format '{$currentFormat}'";
        
        if ($operation) {
            $message .= " for {$operation}";
        }
        
        $message .= " on {$provider}. Supported formats: " . implode(', ', $allowedFormats);
        
        return new self(
            $message,
            $provider,
            [
                'file_path' => $filePath,
                'current_format' => $currentFormat,
                'allowed_formats' => $allowedFormats,
                'operation' => $operation,
                'validation_type' => 'file_format'
            ],
            null,
            null,
            null
        );
    }

    /**
     * Create exception for invalid voice parameter.
     *
     * @param   string $voice          The invalid voice name
     * @param   array  $availableVoices Array of available voices
     * @param   string $provider       The provider name
     *
     * @return  self
     * @since  __DEPLOY_VERSION__
     */
    public static function invalidVoice(string $voice, array $availableVoices, string $provider): self
    {
        $message = "Voice '{$voice}' is not available on {$provider}. Available voices: " . implode(', ', $availableVoices);
        
        return new self(
            $message,
            $provider,
            [
                'requested_voice' => $voice,
                'available_voices' => $availableVoices,
                'validation_type' => 'voice'
            ],
            null,
            null,
            null
        );
    }

    /**
     * Create exception for empty or invalid message array.
     *
     * @param   string $provider  The provider name
     * @param   string $reason    Specific reason for validation failure
     *
     * @return  self
     * @since  __DEPLOY_VERSION__
     */
    public static function invalidMessages(string $provider, string $reason = 'Messages array cannot be empty'): self
    {
        return new self(
            $reason,
            $provider,
            [
                'validation_type' => 'messages',
                'requirement' => 'non-empty array with valid message structure'
            ],
            null,
            null,
            null
        );
    }

    /**
     * Create exception for invalid image size parameter.
     *
     * @param   string $size           The invalid size
     * @param   array  $allowedSizes   Array of allowed sizes
     * @param   string $provider       The provider name
     * @param   string $model          The model being used
     *
     * @return  self
     * @since  __DEPLOY_VERSION__
     */
    public static function invalidImageSize(string $size, array $allowedSizes, string $provider, string $model): self
    {
        $message = "Image size '{$size}' is not supported";
        
        if ($model) {
            $message .= " for model '{$model}'.";
        }

        $message .= " Supported sizes: " . implode(', ', $allowedSizes);

        return new self(
            $message,
            $provider,
            [
                'requested_size' => $size,
                'allowed_sizes' => $allowedSizes,
                'model' => $model,
                'validation_type' => 'image_size'
            ],
            null,
            null,
            null
        );
    }

    /**
     * Create exception for invalid parameter value.
     *
     * @param   string $parameter     The parameter name
     * @param   mixed  $value         The invalid value
     * @param   string $provider      The provider name
     * @param   string $requirement   Description of what's required
     * @param   array  $context       Additional context information
     *
     * @return  self
     * @since  __DEPLOY_VERSION__
     */
    public static function invalidParameter(string $parameter, $value, string $provider, string $requirement, array $context = []): self
    {
        $valueStr = is_scalar($value) ? (string)$value : gettype($value);
        $message = "Parameter '{$parameter}' has invalid value '{$valueStr}'. {$requirement}";
        
        $contextData = array_merge([
            'parameter' => $parameter,
            'invalid_value' => $value,
            'requirement' => $requirement,
            'validation_type' => 'parameter'
        ], $context);
        
        return new self($message, $provider, $contextData, null, null, null);
    }

    /**
     * Create exception for missing required parameter.
     *
     * @param   string $parameter  The missing parameter name
     * @param   string $provider   The provider name
     * @param   string $method     The method being called
     *
     * @return  self
     * @since  __DEPLOY_VERSION__
     */
    public static function missingParameter(string $parameter, string $provider, string $method = ''): self
    {
        $message = "Required parameter '{$parameter}' is missing";
        
        if ($method) {
            $message .= " for {$method}()";
        }
                
        return new self(
            $message,
            $provider,
            [
                'missing_parameter' => $parameter,
                'method' => $method,
                'validation_type' => 'missing_parameter'
            ],
            null,
            null,
            null
        );
    }

    /**
     * Create exception for file not found.
     *
     * @param   string $filePath  The file path that wasn't found
     * @param   string $provider  The provider name
     *
     * @return  self
     * @since  __DEPLOY_VERSION__
     */
    public static function fileNotFound(string $filePath, string $provider): self
    {
        return new self(
            "File '{$filePath}' not found or is not readable",
            $provider,
            [
                'file_path' => $filePath,
                'validation_type' => 'file_existence'
            ],
            null,
            null,
            null
        );
    }

    /**
     * Check if this validation exception is retryable.
     *
     * @return  bool  Always false for validation errors
     * @since  __DEPLOY_VERSION__
     */
    public function isRetryable(): bool
    {
        return false;
    }
}
