<?php

namespace App\Providers;

use App\Adapters\Outbound\Persistence\LaravelTransactionRunner;
use App\Adapters\Outbound\Persistence\PostgresCategoryRepository;
use App\Adapters\Outbound\Persistence\PostgresKitRepository;
use App\Adapters\Outbound\Persistence\PostgresLoanRepository;
use App\Adapters\Outbound\Persistence\PostgresParticipantRepository;
use App\Adapters\Outbound\Persistence\PostgresReportReader;
use App\Adapters\Outbound\Persistence\PostgresUserRepository;
use App\Adapters\Outbound\Security\Argon2idPasswordHasher;
use App\Adapters\Outbound\Security\JwtTokenIssuer;
use App\Adapters\Outbound\Storage\LocalDiskPhotoStorage;
use App\Adapters\Outbound\SystemClock;
use App\Application\Ports\CategoryRepository;
use App\Application\Ports\Clock;
use App\Application\Ports\KitRepository;
use App\Application\Ports\LoanRepository;
use App\Application\Ports\ParticipantRepository;
use App\Application\Ports\PasswordHasher;
use App\Application\Ports\PhotoStorage;
use App\Application\Ports\ReportReader;
use App\Application\Ports\TokenIssuer;
use App\Application\Ports\TransactionRunner;
use App\Application\Ports\UserRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

/**
 * ÚNICO punto de composición (artículo III): aquí, y solo aquí, cada puerto se enlaza con su adaptador.
 * La prueba de arquitectura (T-19) falla si otra clase fuera de Adapters/Outbound nombra un adaptador.
 * Cambiar de tecnología (por ejemplo, fotos en la nube) es cambiar una línea de este archivo (artículo IV).
 *
 * Los casos de uso (App\Application\Services) no se registran: Laravel los arma solo, porque sus constructores
 * piden puertos y los puertos están enlazados aquí.
 */
final class PortBindingsServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> puerto => adaptador */
    public array $singletons = [
        Clock::class => SystemClock::class,
        PasswordHasher::class => Argon2idPasswordHasher::class,
        CategoryRepository::class => PostgresCategoryRepository::class,
        KitRepository::class => PostgresKitRepository::class,
        ParticipantRepository::class => PostgresParticipantRepository::class,
        UserRepository::class => PostgresUserRepository::class,
        LoanRepository::class => PostgresLoanRepository::class,
        ReportReader::class => PostgresReportReader::class,
        TransactionRunner::class => LaravelTransactionRunner::class,
    ];

    public function register(): void
    {
        // Los repositorios piden ConnectionInterface: la conexión por defecto (pgsql, esquema robotics)
        $this->app->bind(ConnectionInterface::class, fn () => DB::connection());

        $this->app->singleton(TokenIssuer::class, fn ($app) => new JwtTokenIssuer(
            (string) config('robotics.jwt_secret'),
            $app->make(Clock::class),
        ));

        $this->app->singleton(PhotoStorage::class, fn () => new LocalDiskPhotoStorage((string) config('robotics.photos_dir')));
    }
}
