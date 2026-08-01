<?php

namespace SummerCraft\Service\Tests\Unit\Language;

use PHPUnit\Framework\TestCase;
use SummerCraft\Core\Context\ApplicationContext;
use SummerCraft\Service\Language\DefaultLanguageService;
use SummerCraft\Service\Language\LanguageConfig;

/**
 * An alias is "volume:source text", and the text after the colon is what an install
 * with no translation shows. Two things made that unreliable: the alias was split on
 * every colon, so any value containing one was truncated, and a value that cannot be
 * written as a key — an email body, or the same wording needing two translations —
 * had nowhere to live.
 */
class DefaultLanguageServiceTest extends TestCase
{
    private function service(): DefaultLanguageService
    {
        $config = new LanguageConfig();
        $config->isGenerateVolumeOnUnknown = false;

        return new DefaultLanguageService(
            $config,
            ApplicationContext::create(
                isCli: true,
                configLoader: 'None',
                basePath: sys_get_temp_dir() . '/no-such-app/'
            )
        );
    }

    /**
     * A generated volume is required back as php, so what is written into it has to
     * be php — an apostrophe in the fallback text used to produce a file that no
     * longer parses, and taking down every later request with it.
     */
    public function testAGeneratedVolumeSurvivesTextWithQuotesInIt(): void
    {
        $temporaryPath = sys_get_temp_dir() . '/language-writer-' . uniqid() . '/';
        $config = new LanguageConfig();
        $config->isGenerateVolumeOnUnknown = true;
        $service = new DefaultLanguageService(
            $config,
            ApplicationContext::create(
                isCli: true,
                configLoader: 'None',
                basePath: sys_get_temp_dir() . '/no-such-app/',
                temporaryPath: $temporaryPath
            )
        );

        $service->get("auth:Don't have an account? It's free");

        $written = $temporaryPath . 'language/' . $config->defaultLanguage . '/auth.php';
        self::assertFileExists($written);

        // required, not read: what matters is that php can still parse it
        $lang = self::requireVolume($written);
        self::assertSame("Don't have an account? It's free", $lang["auth:Don't have an account? It's free"] ?? null);
    }

    /**
     * @return array<string, string>
     */
    private static function requireVolume(string $file): array
    {
        $lang = [];
        require $file;

        return $lang;
    }

    public function testUntranslatedAliasFallsBackToItsOwnSourceText(): void
    {
        self::assertSame('User not found', $this->service()->get('auth_sign:User not found'));
    }

    public function testSourceTextMayContainAColon(): void
    {
        self::assertSame(
            'Follow the link: https://example.test/confirm',
            $this->service()->get('auth_email:Follow the link: https://example.test/confirm')
        );
    }

    public function testExplicitDefaultWinsOverTheTextAfterTheColon(): void
    {
        self::assertSame(
            'Someone asked for a password link at {#siteUrl}',
            $this->service()->get('auth_email:recovery_body', default: 'Someone asked for a password link at {#siteUrl}')
        );
    }

    public function testReplacePairsApplyToTheFallbackToo(): void
    {
        self::assertSame(
            'Verify your email on Example',
            $this->service()->get('auth_email:Verify your email on {#siteName}', ['{#siteName}' => 'Example'])
        );
    }

    public function testAnAliasWithoutAVolumeFallsBackToItself(): void
    {
        self::assertSame('plain', $this->service()->get('plain'));
    }
}
