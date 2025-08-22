<?php

/**
 * Part of the Joomla Framework AI Package
 *
 * @copyright  (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\AI\Interface;

use Joomla\AI\Response\Response;

/**
 * AI provider audio capability interface.
 *
 * @since  __DEPLOY_VERSION__
 */
interface AudioInterface
{
    /**
     * Generate speech audio from text input.
     *
     * @param   string  $text     The text to convert to speech
     * @param   array   $options  Additional options for speech generation
     *
     * @return  Response
     * @since   __DEPLOY_VERSION__
     */
    public function speech(string $text, array $options = []): Response;

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

    /**
     * Transcribe audio to text.
     *
     * @param   string  $audioFile  Path to the audio file to transcribe
     * @param   array   $options    Additional options for transcription
     *
     * @return  Response
     * @since   __DEPLOY_VERSION__
     */
    public function transcribe(string $audioFile, array $options = []): Response;

    /**
     * Translate audio to English text.
     *
     * @param   string  $audioFile  Path to audio file to translate
     * @param   array   $options    Additional options
     *
     * @return  Response
     * @since   __DEPLOY_VERSION__
     */
    public function translate(string $audioFile, array $options = []): Response;
}
