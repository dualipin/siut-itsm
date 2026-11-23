<?php

use App\Enums\PostType;
use App\Filament\Widgets\AgremiadoAnnouncementsWidget;
use App\Models\Post;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    Filament::setCurrentPanel(Filament::getPanel('portal'));
});

test('agremiado sees up to 5 active aviso and noticia posts ordered newest first on portal dashboard', function () {
    $agremiado = User::factory()->agremiado()->create(['is_active' => true]);
    $author = User::factory()->admin()->create(['is_active' => true]);

    $oldest = Post::factory()->create([
        'author_id' => $author->id,
        'type' => PostType::Aviso,
        'title' => 'Comunicado Antiguo Excluido',
        'content' => '<p>Contenido antiguo que ya no cabe en los 5 recientes.</p>',
        'expires_at' => now()->addDays(10),
        'created_at' => now()->subDays(10),
    ]);

    $fifth = Post::factory()->create([
        'author_id' => $author->id,
        'type' => PostType::Noticia,
        'title' => 'Quinto Comunicado Vigente',
        'content' => '<p>Extracto del quinto comunicado reciente.</p>',
        'expires_at' => null,
        'created_at' => now()->subDays(5),
    ]);

    $fourth = Post::factory()->create([
        'author_id' => $author->id,
        'type' => PostType::Aviso,
        'title' => 'Cuarto Comunicado Vigente',
        'content' => '<p>Extracto del cuarto comunicado reciente.</p>',
        'expires_at' => now()->addDays(5),
        'created_at' => now()->subDays(4),
    ]);

    $third = Post::factory()->create([
        'author_id' => $author->id,
        'type' => PostType::Noticia,
        'title' => 'Tercer Comunicado Vigente',
        'content' => '<p>Extracto del tercer comunicado reciente.</p>',
        'expires_at' => null,
        'created_at' => now()->subDays(3),
    ]);

    $second = Post::factory()->create([
        'author_id' => $author->id,
        'type' => PostType::Aviso,
        'title' => 'Segundo Comunicado Vigente',
        'content' => '<p>Extracto del segundo comunicado reciente.</p>',
        'expires_at' => now()->addDay(),
        'created_at' => now()->subDays(2),
    ]);

    $newest = Post::factory()->create([
        'author_id' => $author->id,
        'type' => PostType::Aviso,
        'title' => 'Comunicado Más Reciente',
        'content' => '<p>Contenido destacado para la base trabajadora con convocatoria vigente.</p>',
        'expires_at' => now()->addDays(15),
        'created_at' => now()->subDay(),
    ]);

    $newest->addMedia(UploadedFile::fake()->create('convocatoria.pdf', 120, 'application/pdf'))
        ->toMediaCollection('attachments');

    $response = $this->actingAs($agremiado)->get('/portal');

    $response->assertSuccessful();
    $response->assertSee('Comunicados recientes');
    $response->assertSeeInOrder([
        $newest->title,
        $second->title,
        $third->title,
        $fourth->title,
        $fifth->title,
    ]);
    $response->assertDontSee($oldest->title);
    $response->assertSee('Aviso');
    $response->assertSee('Noticia');
    $response->assertSee('Contenido destacado para la base trabajadora');
    $response->assertSee('1 adjunto');
});

test('expired posts and posts of other types are excluded from announcements widget', function () {
    $agremiado = User::factory()->agremiado()->create(['is_active' => true]);
    $author = User::factory()->admin()->create(['is_active' => true]);

    $activeAviso = Post::factory()->create([
        'author_id' => $author->id,
        'type' => PostType::Aviso,
        'title' => 'Aviso Vigente Visible',
        'expires_at' => now()->addDays(3),
    ]);

    $expiredAviso = Post::factory()->create([
        'author_id' => $author->id,
        'type' => PostType::Aviso,
        'title' => 'Aviso Expirado Oculto',
        'expires_at' => now()->subDay(),
    ]);

    $expiredNoticia = Post::factory()->create([
        'author_id' => $author->id,
        'type' => PostType::Noticia,
        'title' => 'Noticia Expirada Oculta',
        'expires_at' => now()->subDays(2),
    ]);

    $otherPosts = [
        Post::factory()->create(['author_id' => $author->id, 'type' => PostType::Gestion, 'title' => 'Gestión Oculta', 'expires_at' => null]),
        Post::factory()->create(['author_id' => $author->id, 'type' => PostType::Contratos, 'title' => 'Contrato Oculto', 'expires_at' => null]),
        Post::factory()->create(['author_id' => $author->id, 'type' => PostType::Formato, 'title' => 'Formato Oculto', 'expires_at' => null]),
        Post::factory()->create(['author_id' => $author->id, 'type' => PostType::Acervo, 'title' => 'Acervo Oculto', 'expires_at' => null]),
    ];

    $response = $this->actingAs($agremiado)->get('/portal');

    $response->assertSuccessful();
    $response->assertSee($activeAviso->title);
    $response->assertDontSee($expiredAviso->title);
    $response->assertDontSee($expiredNoticia->title);

    foreach ($otherPosts as $otherPost) {
        $response->assertDontSee($otherPost->title);
    }
});

test('empty state is displayed when no active aviso or noticia posts exist', function () {
    $agremiado = User::factory()->agremiado()->create(['is_active' => true]);
    $author = User::factory()->admin()->create(['is_active' => true]);

    // Only an expired aviso and a Formato post exist
    Post::factory()->create([
        'author_id' => $author->id,
        'type' => PostType::Aviso,
        'title' => 'Aviso Vencido',
        'expires_at' => now()->subDay(),
    ]);
    Post::factory()->create([
        'author_id' => $author->id,
        'type' => PostType::Formato,
        'title' => 'Formato No Comunicado',
        'expires_at' => null,
    ]);

    $response = $this->actingAs($agremiado)->get('/portal');

    $response->assertSuccessful();
    $response->assertSee('Comunicados recientes');
    $response->assertSee('No hay comunicados vigentes por el momento.');
    $response->assertDontSee('Aviso Vencido');
});

test('admin and leader do not see announcements widget on portal dashboard', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $leader = User::factory()->lider()->create(['is_active' => true]);

    Post::factory()->create([
        'author_id' => $admin->id,
        'type' => PostType::Aviso,
        'title' => 'Aviso Exclusivo Para Widget Agremiado',
        'expires_at' => null,
    ]);

    $this->actingAs($admin);
    expect(AgremiadoAnnouncementsWidget::canView())->toBeFalse();

    $adminResponse = $this->get('/portal');
    $adminResponse->assertSuccessful();
    $adminResponse->assertDontSee('Comunicados recientes');
    $adminResponse->assertDontSee('Aviso Exclusivo Para Widget Agremiado');

    $this->actingAs($leader);
    expect(AgremiadoAnnouncementsWidget::canView())->toBeFalse();

    $leaderResponse = $this->get('/portal');
    $leaderResponse->assertSuccessful();
    $leaderResponse->assertDontSee('Comunicados recientes');
    $leaderResponse->assertDontSee('Aviso Exclusivo Para Widget Agremiado');
});

test('agremiado can open quick read modal with full content and attachment download links', function () {
    $agremiado = User::factory()->agremiado()->create(['is_active' => true]);
    $author = User::factory()->admin()->create(['is_active' => true]);

    $post = Post::factory()->create([
        'author_id' => $author->id,
        'type' => PostType::Noticia,
        'title' => 'Asamblea General Ordinaria',
        'content' => '<p>Se convoca a <strong>todos los agremiados</strong> a la asamblea general.</p><p>Favor de revisar el orden del día adjunto.</p>',
        'expires_at' => now()->addDays(10),
    ]);

    $firstMedia = $post->addMedia(UploadedFile::fake()->create('orden-del-dia.pdf', 150, 'application/pdf'))
        ->toMediaCollection('attachments');
    $secondMedia = $post->addMedia(UploadedFile::fake()->create('anexo-informativo.docx', 90, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'))
        ->toMediaCollection('attachments');

    $this->actingAs($agremiado);

    Livewire::test(AgremiadoAnnouncementsWidget::class)
        ->assertSuccessful()
        ->mountAction('readAnnouncement', ['post' => $post->id])
        ->assertActionMounted('readAnnouncement')
        ->assertMountedActionModalSee('Asamblea General Ordinaria')
        ->assertMountedActionModalSee('Noticia')
        ->assertMountedActionModalSee('Se convoca a')
        ->assertMountedActionModalSeeHtml('<strong>todos los agremiados</strong>')
        ->assertMountedActionModalSee('Favor de revisar el orden del día adjunto.')
        ->assertMountedActionModalSee('orden-del-dia.pdf')
        ->assertMountedActionModalSee('anexo-informativo.docx')
        ->assertMountedActionModalSeeHtml(route('publications.attachment.download', ['media' => $firstMedia]))
        ->assertMountedActionModalSeeHtml(route('publications.attachment.download', ['media' => $secondMedia]));
});

test('quick read modal does not expose expired or non announcement posts', function () {
    $agremiado = User::factory()->agremiado()->create(['is_active' => true]);
    $author = User::factory()->admin()->create(['is_active' => true]);

    $expiredPost = Post::factory()->create([
        'author_id' => $author->id,
        'type' => PostType::Aviso,
        'title' => 'Aviso Expirado Secreto',
        'content' => '<p>Contenido expirado que no debe abrirse.</p>',
        'expires_at' => now()->subDay(),
    ]);

    $contractPost = Post::factory()->create([
        'author_id' => $author->id,
        'type' => PostType::Contratos,
        'title' => 'Contrato No Listado',
        'content' => '<p>Contenido de contrato que no pertenece al widget.</p>',
        'expires_at' => null,
    ]);

    $this->actingAs($agremiado);

    Livewire::test(AgremiadoAnnouncementsWidget::class)
        ->mountAction('readAnnouncement', ['post' => $expiredPost->id])
        ->assertMountedActionModalSee('Comunicado no disponible')
        ->assertMountedActionModalDontSee('Contenido expirado que no debe abrirse.');

    Livewire::test(AgremiadoAnnouncementsWidget::class)
        ->mountAction('readAnnouncement', ['post' => $contractPost->id])
        ->assertMountedActionModalSee('Comunicado no disponible')
        ->assertMountedActionModalDontSee('Contenido de contrato que no pertenece al widget.');
});
