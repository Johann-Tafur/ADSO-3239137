<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * T-19: vigila los artículos I, II y III de docs/constitution.md.
 *
 * - Artículo I:   app/Domain solo usa PHP puro y su propio namespace.
 * - Artículo II:  app/Application solo conoce el dominio y sus propios puertos.
 * - Artículo III: solo PortBindingsServiceProvider conoce los adaptadores de salida.
 *
 * No arranca Laravel: lee los archivos .php como texto.
 */
final class LayerDependenciesTest extends TestCase
{
    private const APP = __DIR__.'/../../app';

    private const COMPOSITION_ROOT = 'Providers/PortBindingsServiceProvider.php';

    // ---------- Las reglas sobre el código real ----------

    public function test_domain_only_depends_on_itself_and_plain_php(): void
    {
        $this->addToAssertionCount(1); // la carpeta puede estar vacía mientras se construye
        foreach ($this->phpFilesIn('Domain') as $file => $code) {
            foreach ($this->importsOf($code) as $import) {
                $this->assertTrue(
                    $this->isAllowedInDomain($import),
                    "Artículo I: {$file} importa {$import}",
                );
            }
        }
    }

    public function test_application_does_not_depend_on_framework_or_adapters(): void
    {
        $this->addToAssertionCount(1);
        foreach ($this->phpFilesIn('Application') as $file => $code) {
            foreach ($this->importsOf($code) as $import) {
                $this->assertTrue(
                    $this->isAllowedInApplication($import),
                    "Artículo II: {$file} importa {$import}",
                );
            }
        }
    }

    public function test_only_the_composition_root_knows_outbound_adapters(): void
    {
        $this->addToAssertionCount(1);
        foreach ($this->phpFilesIn('') as $file => $code) {
            if ($file === self::COMPOSITION_ROOT || str_starts_with($file, 'Adapters/Outbound/')) {
                continue;
            }
            $this->assertFalse(
                $this->mentionsOutboundAdapter($code),
                "Artículo III: {$file} usa un adaptador de salida; solo puede hacerlo ".self::COMPOSITION_ROOT,
            );
        }
    }

    // ---------- Prueba de que el vigilante sí detecta violaciones ----------

    public function test_the_checker_rejects_forbidden_imports(): void
    {
        $badDomain = "<?php\nnamespace App\\Domain;\nuse Illuminate\\Support\\Str;\nuse App\\Adapters\\Inbound\\X;\n";
        foreach ($this->importsOf($badDomain) as $import) {
            $this->assertFalse($this->isAllowedInDomain($import), "Debió rechazar {$import} en el dominio");
        }

        $this->assertFalse($this->isAllowedInDomain('Carbon\\Carbon'), 'Un paquete de vendor no entra al dominio');
        $this->assertFalse($this->isAllowedInDomain('App\\Application\\Ports\\Clock'), 'El dominio no conoce la aplicación');
        $this->assertFalse($this->isAllowedInApplication('Illuminate\\Support\\Facades\\DB'));
        $this->assertFalse($this->isAllowedInApplication('App\\Adapters\\Outbound\\SystemClock'));
        $this->assertTrue($this->mentionsOutboundAdapter('$x = new \\App\\Adapters\\Outbound\\SystemClock();'));
        $this->assertTrue($this->mentionsOutboundAdapter("use App\\Adapters\\Outbound\\Persistence\\PgKitRepository;"));
    }

    public function test_the_checker_accepts_allowed_imports(): void
    {
        $this->assertTrue($this->isAllowedInDomain('DateTimeImmutable'));
        $this->assertTrue($this->isAllowedInDomain('App\\Domain\\ValueObjects\\Units'));
        $this->assertTrue($this->isAllowedInApplication('App\\Domain\\Kit'));
        $this->assertTrue($this->isAllowedInApplication('App\\Application\\Ports\\Clock'));
        $this->assertFalse($this->mentionsOutboundAdapter('use App\\Application\\Ports\\Clock;'));
    }

    // ---------- Reglas ----------

    private function isAllowedInDomain(string $import): bool
    {
        return $this->isPlainPhp($import) || str_starts_with($import, 'App\\Domain\\');
    }

    private function isAllowedInApplication(string $import): bool
    {
        return $this->isPlainPhp($import)
            || str_starts_with($import, 'App\\Domain\\')
            || str_starts_with($import, 'App\\Application\\');
    }

    /** Una clase de PHP sin namespace: DateTimeImmutable, InvalidArgumentException, Throwable... */
    private function isPlainPhp(string $import): bool
    {
        return ! str_contains($import, '\\');
    }

    private function mentionsOutboundAdapter(string $code): bool
    {
        return preg_match('/\\\\?App\\\\Adapters\\\\Outbound\\\\/', $code) === 1;
    }

    // ---------- Lectura de archivos ----------

    /** @return list<string> lo que aparece después de cada "use" de nivel superior */
    private function importsOf(string $code): array
    {
        preg_match_all('/^use\s+(?:function\s+|const\s+)?\\\\?([A-Za-z0-9_\\\\]+)/m', $code, $matches);

        return $matches[1];
    }

    /** @return array<string, string> ruta relativa a app/ => contenido */
    private function phpFilesIn(string $folder): array
    {
        $root = realpath(self::APP);
        $dir = $root.($folder === '' ? '' : DIRECTORY_SEPARATOR.$folder);
        if (! is_dir($dir)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                $files[$relative] = file_get_contents($file->getPathname());
            }
        }

        return $files;
    }
}
