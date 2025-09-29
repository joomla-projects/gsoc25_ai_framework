<?php

namespace Joomla\AI\Tests\Provider\OpenAIProvider;

use Joomla\AI\AIFactory; 
use PHPUnit\Framework\TestCase;

class ImageInterfaceTest extends TestCase
{
    protected $config;
    protected $api_key, $api_key_2;
    protected $base_url;
    protected $provider, $provider2;

    protected function setUp(): void
    {
        parent::setUp();

        $configFile = __DIR__ . '/../../config.json';
        $this->config = json_decode(file_get_contents($configFile), true);
        $this->api_key = $this->config['gpt_image_model_key'] ?? $this->config['openai_api_key'] ?? null;
        $this->base_url = $this->config['openai_base_url'] ?? null;
        $this->api_key_2 = $this->config['openai_api_key'] ?? null;

        $this->provider = AIFactory::getAI('openai', [
            'api_key'  => $this->api_key,
            'base_url' => $this->base_url,
        ]);

        $this->provider2 = AIFactory::getAI('openai', [
            'api_key' => $this->api_key_2,
        ]);
    }

    protected function saveOutputContentToFile(string $content, string $path): int
    {
        // Ensure output dir exists
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        // Detect base64 payload
        if (preg_match('#^[A-Za-z0-9+/=\s]+$#', $content) && strlen($content) % 4 === 0) {
            $data = base64_decode($content, true);
            if ($data === false) {
                return 0;
            }
            file_put_contents($path, $data);
            return filesize($path);
        }

        // Otherwise try to treat as URL
        if (filter_var($content, FILTER_VALIDATE_URL)) {
            $data = @file_get_contents($content);
            if ($data === false) {
                return 0;
            }
            file_put_contents($path, $data);
            return filesize($path);
        }

        // Unknown format
        return 0;
    }

    public function testGenerateImageBasic()
    {
        echo "Test 1: Basic Image Generation\n";
        $response = $this->provider->generateImage(
            "A cute baby sea otter floating on its back in crystal clear water, photorealistic",
            ['model' => 'gpt-image-1', 'size' => '1024x1024']
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getContent());
        $this->assertEquals('OpenAI', $response->getProvider());

        $meta = $response->getMetadata();
        $this->assertStringContainsString('gpt-image-1', $meta['model']);

        $outFile = __DIR__ . '/output/test1_basic_sea_otter.png';
        $written = $this->saveOutputContentToFile($response->getContent(), $outFile);
        $this->assertGreaterThan(0, $written, 'Failed to save edited image to disk');
        $this->assertFileExists($outFile);
    }

    public function testSingleImageEdit()
    {
        echo "Test 2: Single Image Editing\n";
        $source = __DIR__ . '/../test_files/fish.png';
        if (!file_exists($source)) {
            $this->markTestSkipped('Source test image not available: ' . $source);
            return;
        }

        $response = $this->provider->editImage(
            $source,
            'Change the colour of the fish to green',
            ['model' => 'gpt-image-1', 'output_format' => 'png']
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getContent());

        $meta = $response->getMetadata();
        $this->assertStringContainsString('gpt-image-1', $meta['model']);

        $outFile = __DIR__ . '/output/edited_fish.png';
        $written = $this->saveOutputContentToFile($response->getContent(), $outFile);
        $this->assertGreaterThan(0, $written, 'Failed to save edited image to disk');
        $this->assertFileExists($outFile);
    }

    public function testEditWithTransparency()
    {
        echo "Test 3: Editing with Transparent Background\n";
        $source = __DIR__ . '/../test_files/fish.png';
        if (!file_exists($source)) {
            $this->markTestSkipped('Source test image not available: ' . $source);
            return;
        }

        $response = $this->provider->editImage(
            $source,
            'Extract the main subject and remove the background, creating a clean isolated object suitable for logos',
            [
                'model' => 'gpt-image-1',
                'background' => 'transparent',
                'output_format' => 'png',
                'size' => '1024x1024',
            ]
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getContent());

        $meta = $response->getMetadata();
        $this->assertStringContainsString('gpt-image-1', $meta['model']);

        $outFile = __DIR__ . '/output/edited_transparent.png';
        $written = $this->saveOutputContentToFile($response->getContent(), $outFile);
        $this->assertGreaterThan(0, $written, 'Failed to save transparent edited image to disk');
        $this->assertFileExists($outFile);
    }

    public function testMultipleImageEditIfAvailable()
    {
        echo "Test 4: Test model with multiple images\n";
        $file1 = __DIR__ . '/output/test1_basic_sea_otter.png';
        $file2 = __DIR__ . '/output/edited_transparent.png';

        if (!file_exists($file1) || !file_exists($file2)) {
            $this->markTestSkipped('Previous generated images not available for multi-image test.');
            return;
        }

        $response = $this->provider->editImage(
            [$file1, $file2],
            'Combine these images into an artistic collage',
            ['model' => 'gpt-image-1']
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getContent());

        $meta = $response->getMetadata();
        $this->assertStringContainsString('gpt-image-1', $meta['model']);

        $outFile = __DIR__ . '/output/multi_image_test.png';
        $written = $this->saveOutputContentToFile($response->getContent(), $outFile);
        $this->assertGreaterThan(0, $written, 'Failed to save multi-image output to disk');
        $this->assertFileExists($outFile);
    }

    public function testSingleImageEditIfSourceAvailable()
    {
        echo "Test 5: Single Image Edit with DALL-E 2\n";
        $source = __DIR__ . '/../test_files/dog_img.png';
        $mask   = __DIR__ . '/../test_files/mask_dog_img.png';

        $options = [
            'model' => 'dall-e-2',
            'mask' => $mask,
            'response_format' => 'b64_json'
        ];

        $response = $this->provider2->editImage($source, 'picture of a dog and a rabbit', $options);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getContent());

        $outFile = __DIR__ . '/output/edited_dog.png';
        $written = $this->saveOutputContentToFile($response->getContent(), $outFile);
        $this->assertGreaterThan(0, $written, 'Failed to save edited image to disk');
        $this->assertFileExists($outFile);
    }

    public function testDallE3Base64()
    {
        echo "Test 6: DALL-E 3 with Base64 response...\n";
        $response = $this->provider2->generateImage(
            "A red apple on a white table",
            ['model' => 'dall-e-3', 'response_format' => 'b64_json']
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getContent());
        $this->assertEquals('OpenAI', $response->getProvider());

        $meta = $response->getMetadata();
        $this->assertArrayHasKey('response_format', $meta);
        $this->assertStringContainsString('b64', $meta['response_format']);

        $outFile = __DIR__ . '/output/dalle3_base64.png';
        $written = $this->saveOutputContentToFile($response->getContent(), $outFile);
        $this->assertGreaterThan(0, $written, 'Failed to save DALL-E 3 base64 image to disk');
    }

    public function testDallE3Url()
    {
        echo "Test 7: DALL-E 3 with URL response...\n";
        $response = $this->provider2->generateImage(
            "A blue ocean with waves",
            ['model' => 'dall-e-3', 'response_format' => 'url']
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getContent());
        $this->assertEquals('OpenAI', $response->getProvider());

        $meta = $response->getMetadata();
        $this->assertArrayHasKey('response_format', $meta);
        $this->assertStringContainsString('url', $meta['response_format']);

        // content may be a single URL or JSON list — ensure we got at least one URL-like string
        $content = $response->getContent();
        $urls = json_decode($content, true);
        if (is_array($urls)) {
            $this->assertGreaterThanOrEqual(1, count($urls));
            $this->assertTrue(filter_var($urls[0], FILTER_VALIDATE_URL) !== false);
        } else {
            $this->assertTrue(filter_var($content, FILTER_VALIDATE_URL) !== false);
        }
    }

    public function testDallE2Base64Multiple()
    {
        echo "Test 8: DALL-E 2 with Base64 response...\n";
        $response = $this->provider2->generateImage(
            "A simple drawing of a house",
            ['model' => 'dall-e-2', 'response_format' => 'b64_json', 'n' => 2]
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getContent());
        $this->assertEquals('OpenAI', $response->getProvider());

        $meta = $response->getMetadata();
        $this->assertArrayHasKey('response_format', $meta);
        $this->assertArrayHasKey('image_count', $meta);
        $this->assertEquals(2, $meta['image_count']); // 2 choices returned
        $this->assertStringContainsString('b64', $meta['response_format']);
    }

    public function testDallE2Url()
    {
        echo "Test 9: DALL-E 2 with URL response...\n";
        $response = $this->provider2->generateImage(
            "A cartoon cat wearing sunglasses",
            ['model' => 'dall-e-2', 'response_format' => 'url']
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getContent());
        $this->assertEquals('OpenAI', $response->getProvider());

        $meta = $response->getMetadata();
        $this->assertArrayHasKey('response_format', $meta);
        $this->assertStringContainsString('url', $meta['response_format']);
    }

    public function testCreateImageVariations()
    {
        echo "Test 10: Create image variations...\n";
        $source = __DIR__ . '/../../test_files/fish.png';

        $options = [
            'model' => 'dall-e-2',
            'n' => 3,
            'size' => '512x512',
            'response_format' => 'url'
        ];

        $response = $this->provider2->createImageVariation($source, $options);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OpenAI', $response->getProvider());
        $this->assertNotEmpty($response->getContent());

        $meta = $response->getMetadata();
        $this->assertArrayHasKey('response_format', $meta);
        $this->assertArrayHasKey('image_count', $meta);
        $this->assertEquals(3, $meta['image_count']);
        $this->assertStringContainsString('url', $meta['response_format']);
    }
}
