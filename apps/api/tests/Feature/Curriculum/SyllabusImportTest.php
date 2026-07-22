<?php

declare(strict_types=1);

use App\Enums\LessonType;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Program;
use App\Models\Tenant;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $this->admin->assignRole('admin');
    Sanctum::actingAs($this->admin);
});

function csvFile(string $body): UploadedFile
{
    return UploadedFile::fake()->createWithContent('syllabus.csv', $body);
}

/** Column index (0-based) → spreadsheet letter: 0 → A, 26 → AA. */
function colLetter(int $i): string
{
    $s = '';
    for ($n = $i + 1; $n > 0; $n = intdiv($n - 1, 26)) {
        $s = chr(65 + ($n - 1) % 26).$s;
    }

    return $s;
}

/**
 * Build a real (namespaced, shared-string) .xlsx from a grid and return it as an
 * uploaded file — so the reader is exercised the way Excel actually writes files.
 *
 * @param  list<list<string>>  $grid
 */
function xlsxFile(array $grid, string $name = 'syllabus.xlsx'): UploadedFile
{
    $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    // Shared-string table (deduplicated), and each cell's index into it.
    $strings = [];
    $index = static function (string $v) use (&$strings): int {
        $key = array_search($v, $strings, true);
        if ($key === false) {
            $strings[] = $v;
            $key = array_key_last($strings);
        }

        return (int) $key;
    };

    $sheetRows = '';
    foreach ($grid as $r => $row) {
        $cells = '';
        foreach ($row as $c => $value) {
            $ref = colLetter($c).($r + 1);
            $cells .= '<c r="'.$ref.'" t="s"><v>'.$index((string) $value).'</v></c>';
        }
        $sheetRows .= '<row r="'.($r + 1).'">'.$cells.'</row>';
    }

    $si = '';
    foreach ($strings as $s) {
        $si .= '<si><t>'.htmlspecialchars($s, ENT_XML1).'</t></si>';
    }

    $sharedXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<sst xmlns="'.$ns.'" count="'.count($strings).'" uniqueCount="'.count($strings).'">'.$si.'</sst>';
    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<worksheet xmlns="'.$ns.'"><sheetData>'.$sheetRows.'</sheetData></worksheet>';

    $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('xl/sharedStrings.xml', $sharedXml);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();

    return new UploadedFile($path, $name, 'application/octet-stream', null, true);
}

$SAMPLE = <<<'CSV'
program,course,module,topic,lesson_title,lesson_type
Data Engineering,DE Bootcamp,ETL Foundations,Batch vs Streaming,Intro to ETL,notes
Data Engineering,DE Bootcamp,ETL Foundations,Batch vs Streaming,ETL walkthrough,video
Data Engineering,DE Bootcamp,Spark,RDDs,Spark basics,notes
CSV;

it('downloads a CSV template with the expected headers', function () {
    $res = get('/api/v1/admin/syllabus/template')->assertOk();
    expect($res->headers->get('content-type'))->toContain('text/csv');
    expect($res->getContent())->toContain('program,course,module,topic,lesson_title,lesson_type');
});

it('builds the whole tree from a CSV in one upload', function () use ($SAMPLE) {
    postJson('/api/v1/admin/syllabus/import', ['file' => csvFile($SAMPLE)])
        ->assertCreated()
        ->assertJsonPath('data.programs', 1)
        ->assertJsonPath('data.courses', 1)
        ->assertJsonPath('data.modules', 2)
        ->assertJsonPath('data.topics', 2);

    expect(Program::query()->where('name', 'Data Engineering')->exists())->toBeTrue();
    $course = Course::query()->where('name', 'DE Bootcamp')->firstOrFail();
    expect($course->modules()->count())->toBe(2);
});

it('scaffolds Quiz + Assignment + Mock under every topic', function () use ($SAMPLE) {
    postJson('/api/v1/admin/syllabus/import', ['file' => csvFile($SAMPLE)])->assertCreated();

    $topic = Topic::query()->where('name', 'Batch vs Streaming')->firstOrFail();
    $types = $topic->lessons()->pluck('type')->map(fn ($t) => $t instanceof LessonType ? $t->value : $t);

    expect($types)->toContain(LessonType::Quiz->value)
        ->toContain(LessonType::Assignment->value)
        ->toContain(LessonType::MockMilestone->value)
        ->toContain(LessonType::Notes->value)   // the explicit lessons too
        ->toContain(LessonType::Video->value);
});

it('is idempotent — re-importing the same CSV creates nothing new', function () use ($SAMPLE) {
    postJson('/api/v1/admin/syllabus/import', ['file' => csvFile($SAMPLE)])->assertCreated();
    $before = Lesson::query()->count();

    postJson('/api/v1/admin/syllabus/import', ['file' => csvFile($SAMPLE)])
        ->assertCreated()
        ->assertJsonPath('data.topics', 0)
        ->assertJsonPath('data.lessons', 0);

    expect(Lesson::query()->count())->toBe($before);
});

it('rejects a CSV with an invalid lesson_type, importing nothing', function () {
    $bad = "program,course,module,topic,lesson_title,lesson_type\nP,C,M,T,A lesson,banana\n";

    postJson('/api/v1/admin/syllabus/import', ['file' => csvFile($bad)])
        ->assertStatus(422)->assertJsonValidationErrorFor('file');

    expect(Program::query()->count())->toBe(0);
});

it('rejects a CSV missing required columns', function () {
    $bad = "program,course,module\nP,C,M\n";

    postJson('/api/v1/admin/syllabus/import', ['file' => csvFile($bad)])
        ->assertStatus(422)->assertJsonValidationErrorFor('file');
});

it('imports only into the acting tenant (isolation)', function () use ($SAMPLE) {
    $other = Tenant::factory()->create();

    postJson('/api/v1/admin/syllabus/import', ['file' => csvFile($SAMPLE)])->assertCreated();

    // Every created row belongs to the acting tenant; the other tenant is untouched.
    $foreignPrograms = Program::query()->withoutGlobalScopes()->where('tenant_id', $other->id)->count();
    $minePrograms = Program::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('name', 'Data Engineering')->count();
    expect($foreignPrograms)->toBe(0)->and($minePrograms)->toBe(1);
    expect(Course::query()->withoutGlobalScopes()->where('tenant_id', $other->id)->count())->toBe(0);
});

it('denies staff without manage-curriculum', function () use ($SAMPLE) {
    $mentor = User::factory()->for($this->tenant)->create(['user_type' => 'staff']);
    $mentor->assignRole('mentor');
    Sanctum::actingAs($mentor);

    get('/api/v1/admin/syllabus/template')->assertForbidden();
    postJson('/api/v1/admin/syllabus/import', ['file' => csvFile($SAMPLE)])->assertForbidden();
});

/* ---- Excel (.xlsx) upload — same tree, spreadsheet source ---- */

it('builds the whole tree from an .xlsx upload', function () {
    $grid = [
        ['program', 'course', 'module', 'topic', 'lesson_title', 'lesson_type'],
        ['Data Engineering', 'DE Bootcamp', 'ETL Foundations', 'Batch vs Streaming', 'Intro to ETL', 'notes'],
        ['Data Engineering', 'DE Bootcamp', 'ETL Foundations', 'Batch vs Streaming', 'ETL walkthrough', 'video'],
        ['Data Engineering', 'DE Bootcamp', 'Spark', 'RDDs', 'Spark basics', 'notes'],
    ];

    postJson('/api/v1/admin/syllabus/import', ['file' => xlsxFile($grid)])
        ->assertCreated()
        ->assertJsonPath('data.programs', 1)
        ->assertJsonPath('data.courses', 1)
        ->assertJsonPath('data.modules', 2)
        ->assertJsonPath('data.topics', 2);

    $topic = Topic::query()->where('name', 'Batch vs Streaming')->firstOrFail();
    expect($topic->lessons()->where('title', 'Intro to ETL')->exists())->toBeTrue()
        ->and($topic->lessons()->pluck('type')->map(fn ($t) => $t instanceof LessonType ? $t->value : $t))
        ->toContain(LessonType::Quiz->value);
});

it('ignores blank trailing rows in an .xlsx', function () {
    $grid = [
        ['program', 'course', 'module', 'topic', 'lesson_title', 'lesson_type'],
        ['P', 'C', 'M', 'T', 'A lesson', 'notes'],
        ['', '', '', '', '', ''],
    ];

    postJson('/api/v1/admin/syllabus/import', ['file' => xlsxFile($grid)])
        ->assertCreated()
        ->assertJsonPath('data.topics', 1);
});

it('rejects an .xlsx missing required columns', function () {
    $grid = [['program', 'course', 'module'], ['P', 'C', 'M']];

    postJson('/api/v1/admin/syllabus/import', ['file' => xlsxFile($grid)])
        ->assertStatus(422)->assertJsonValidationErrorFor('file');
});

it('rejects a file named .xlsx that is not a workbook', function () {
    $notXlsx = UploadedFile::fake()->createWithContent('syllabus.xlsx', 'this is not a zip');

    postJson('/api/v1/admin/syllabus/import', ['file' => $notXlsx])
        ->assertStatus(422)->assertJsonValidationErrorFor('file');
});
