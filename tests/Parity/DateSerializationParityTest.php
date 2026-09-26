<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Parity;

use Brighten\ImmutableModel\ImmutableModel;
use Brighten\ImmutableModel\Relations\ImmutableMorphPivot;
use Brighten\ImmutableModel\Relations\ImmutablePivot;
use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentUser;
use Brighten\ImmutableModel\Tests\Models\ImmutableUser;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Parity tests for ImmutableModel's native serializeDate().
 *
 * The native formatter must produce exactly the string that Eloquent's
 * Carbon-based serializeDate() produces, for every date type, time zone,
 * precision, year range and Carbon locale.
 */
class DateSerializationParityTest extends ParityTestCase
{
    private string $defaultTimezone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defaultTimezone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->defaultTimezone);
        Carbon::setLocale('en');
        parent::tearDown();
    }

    private function eloquentSerializer(): Model
    {
        return new class extends Model {
            public function serialize(DateTimeInterface $date): ?string
            {
                return $this->serializeDate($date);
            }
        };
    }

    private function immutableSerializer(): ImmutableModel
    {
        return new class extends ImmutableModel {
            public function serialize(DateTimeInterface $date): ?string
            {
                return $this->serializeDate($date);
            }
        };
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function dateProvider(): array
    {
        return [
            'utc midnight' => ['2024-01-01 00:00:00', 'UTC'],
            'microseconds' => ['2024-06-15 13:45:30.123456', 'UTC'],
            'new york winter' => ['2024-01-15 08:00:00', 'America/New_York'],
            'new york summer' => ['2024-07-15 08:00:00', 'America/New_York'],
            'dst gap' => ['2024-03-10 02:30:00', 'America/New_York'],
            'dst overlap' => ['2024-11-03 01:30:00', 'America/New_York'],
            'half hour offset' => ['2024-02-29 23:59:59.999999', 'Asia/Kolkata'],
            'quarter hour offset' => ['2024-05-05 12:00:00', 'Pacific/Chatham'],
            'utc+14 crosses date line' => ['2024-01-01 05:00:00', 'Pacific/Kiritimati'],
            'utc-12' => ['2024-12-31 20:00:00', 'Etc/GMT+12'],
            'fixed offset' => ['2024-04-01 10:00:00', '+03:30'],
            'year 1' => ['0001-01-01 00:00:00', 'UTC'],
            'year 999' => ['0999-12-31 23:59:59', 'UTC'],
            'year 9999' => ['9999-12-31 23:59:59', 'UTC'],
            'year 9999 becomes 10000 in utc' => ['9999-12-31 23:00:00', 'America/New_York'],
            'year 0 (invalid in Carbon)' => ['0000-06-01 12:00:00', 'UTC'],
            'year 1 becomes year 0 in utc' => ['0001-01-01 00:10:00', '+01:00'],
            'year 0 with local mean time' => ['0000-01-01 00:30:00', 'Europe/Paris'],
            'year 10000' => ['10000-01-01 00:00:00', 'UTC'],
            'negative year' => ['-0044-03-15 12:00:00', 'UTC'],
            'unix epoch' => ['1970-01-01 00:00:00', 'UTC'],
            'before epoch' => ['1969-07-20 20:17:40', 'UTC'],
        ];
    }

    /**
     * @return array<int, DateTimeInterface>
     */
    private function allTypes(string $value, string $timezone): array
    {
        // Parsed by hand: PHP cannot parse 5-digit or negative years from a string.
        preg_match('/^(-?\d+)-(\d\d)-(\d\d) (\d\d):(\d\d):(\d\d)(?:\.(\d{6}))?$/', $value, $m);
        $this->assertNotEmpty($m, "Unparseable test date {$value}");

        $date = (new DateTime('now', new DateTimeZone($timezone)))
            ->setDate((int) $m[1], (int) $m[2], (int) $m[3])
            ->setTime((int) $m[4], (int) $m[5], (int) $m[6], (int) ($m[7] ?? 0));

        $types = [$date, DateTimeImmutable::createFromMutable($date)];

        // Carbon cannot represent some years at all (e.g. 10000); skip it there.
        foreach ([Carbon::class, CarbonImmutable::class] as $carbon) {
            try {
                $types[] = $carbon::instance($date);
            } catch (\Throwable) {
            }
        }

        return $types;
    }

    /**
     * Serialize and describe the outcome, so an exception can be compared too.
     */
    private function outcome(object $serializer, DateTimeInterface $date): string
    {
        try {
            return var_export($serializer->serialize($date), true);
        } catch (\Throwable $e) {
            return 'throws ' . get_class($e);
        }
    }

    #[DataProvider('dateProvider')]
    public function test_serialize_date_matches_eloquent(string $value, string $timezone): void
    {
        $eloquent = $this->eloquentSerializer();
        $immutable = $this->immutableSerializer();

        foreach ($this->allTypes($value, $timezone) as $date) {
            $this->assertSame(
                $this->outcome($eloquent, $date),
                $this->outcome($immutable, $date),
                get_class($date) . " {$value} {$timezone}"
            );
        }
    }

    public function test_serialize_date_matches_eloquent_under_other_app_timezones(): void
    {
        $eloquent = $this->eloquentSerializer();
        $immutable = $this->immutableSerializer();

        foreach (['America/Los_Angeles', 'Asia/Tokyo', 'Australia/Lord_Howe'] as $appTimezone) {
            date_default_timezone_set($appTimezone);

            foreach ([new Carbon('2024-03-31 01:30:00'), new DateTime('2024-10-27 02:30:00')] as $date) {
                $this->assertSame($eloquent->serialize($date), $immutable->serialize($date), $appTimezone);
            }
        }
    }

    public function test_serialize_date_matches_eloquent_under_carbon_locales(): void
    {
        $eloquent = $this->eloquentSerializer();
        $immutable = $this->immutableSerializer();
        $date = new Carbon('2024-06-15 13:45:30.123456', 'Europe/Berlin');

        foreach (['ar', 'fa', 'hi', 'th', 'zh_CN', 'ja', 'ru', 'he'] as $locale) {
            Carbon::setLocale($locale);

            $this->assertSame($eloquent->serialize($date), $immutable->serialize($date), $locale);
        }
    }

    public function test_to_array_matches_eloquent(): void
    {
        $this->seedParityTestData();

        $eloquent = EloquentUser::orderBy('id')->get()->toArray();
        $immutable = ImmutableUser::orderBy('id')->get()->toArray();

        foreach ($eloquent as $i => $row) {
            foreach (['created_at', 'updated_at', 'email_verified_at'] as $column) {
                $this->assertSame($row[$column], $immutable[$i][$column], "row {$i} {$column}");
            }
        }
    }

    public function test_pivots_use_native_serialization(): void
    {
        $date = new CarbonImmutable('2024-06-15 13:45:30.123456', 'Asia/Kolkata');
        $expected = $this->eloquentSerializer()->serialize($date);

        foreach ([new ImmutablePivot(), new ImmutableMorphPivot()] as $pivot) {
            $pivot->setRawAttributes(['created_at' => $date]);
            $this->assertSame($expected, $pivot->toArray()['created_at']);
        }
    }
}
