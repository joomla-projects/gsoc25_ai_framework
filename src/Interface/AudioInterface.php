<?php

namespace Joomla\AI\Interface;

use Joomla\AI\Response\Response;

/**
 * Audio interface for AI providers that support audio/speech capabilities.
 *
 * @since  __DEPLOY_VERSION__
 */
interface AudioInterface
{
    /**
     * Generate speech audio from text input.
     *
     * @param   string  $text     The text to convert to speech
     * @param   string  $model    The TTS model to use for speech generation
     * @param   string  $voice    The voice to use for speech generation
     * @param   array   $options  Additional options for speech generation
     *
     * @return  Response
     * @since   __DEPLOY_VERSION__
     */
    public function speech(string $text, string $model, string $voice, array $options = []): Response;

    /**
     * Get available voices for speech generation.
     *
     * @return  array  Array of available voice names
     * @since   __DEPLOY_VERSION__
     */
    public function getAvailableVoices(): array;

    /**
     * Get available TTS models for this provider.
     *
     * @return  array  Array of available TTS model names
     * @since   __DEPLOY_VERSION__
     */
    public function getTTSModels(): array;

    /**
     * Get supported audio output formats.
     *
     * @return  array  Array of supported format names
     * @since   __DEPLOY_VERSION__
     */
    public function getSupportedAudioFormats(): array;
}
