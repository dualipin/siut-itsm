<?php

use App\Enums\PostType;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('public index page renders successfully', function () {
    $post = Post::factory()->create([
        'type' => PostType::Noticia,
        'title' => 'Noticia de Prueba Sindical',
    ]);

    $response = $this->get('/publicaciones');

    $response->assertStatus(200);
    $response->assertSee('Publicaciones Sindicales');
    $response->assertSee('Noticia de Prueba Sindical');
});

test('each post type public page renders successfully with 200 ok', function (PostType $type) {
    $author = User::factory()->create(['name' => 'Autor Sindical']);
    $post = Post::factory()->create([
        'author_id' => $author->id,
        'type' => $type,
        'title' => 'Publicación Especial de '.$type->getLabel(),
        'content' => 'Contenido detallado para '.$type->getLabel(),
    ]);

    $response = $this->get('/publicaciones/'.$type->getSlug());

    $response->assertStatus(200);
    $response->assertSee($type->getPluralLabel());
    $response->assertSee('Publicación Especial de '.$type->getLabel());
})->with([
    'noticias' => PostType::Noticia,
    'avisos' => PostType::Aviso,
    'gestiones' => PostType::Gestion,
    'contratos' => PostType::Contratos,
    'formatos' => PostType::Formato,
    'acervo' => PostType::Acervo,
]);

test('search filter works on publications page', function () {
    $uniquePost = Post::factory()->create([
        'type' => PostType::Aviso,
        'title' => 'Convocatoria Extraordinaria 2026',
        'content' => 'Se convoca a todos los agremiados al salón sindical.',
    ]);

    $otherPost = Post::factory()->create([
        'type' => PostType::Aviso,
        'title' => 'Aviso ordinario semanal',
        'content' => 'Información de rutina.',
    ]);

    $response = $this->get('/publicaciones?search=Convocatoria+Extraordinaria');

    $response->assertStatus(200);
    $response->assertSee('Convocatoria Extraordinaria 2026');
    $response->assertDontSee('Aviso ordinario semanal');
});

test('publication detail page renders correctly', function () {
    $author = User::factory()->create(['name' => 'Secretario General']);
    $post = Post::factory()->create([
        'author_id' => $author->id,
        'type' => PostType::Gestion,
        'title' => 'Firma del Nuevo Convenio 2026',
        'slug' => 'firma-del-nuevo-convenio-2026',
        'content' => '<p>Se logró un acuerdo histórico para todos los trabajadores.</p>',
    ]);

    $response = $this->get('/publicaciones/gestiones/firma-del-nuevo-convenio-2026');

    $response->assertStatus(200);
    $response->assertSee('Firma del Nuevo Convenio 2026');
    $response->assertSee('Se logró un acuerdo histórico');
    $response->assertSee('Secretario General');
    $response->assertSee('Gestiones');
});

test('invalid post type slug returns 404', function () {
    $response = $this->get('/publicaciones/tipo-inexistente');

    $response->assertStatus(404);
});

test('guest can download post attachment', function () {
    $post = Post::factory()->create([
        'type' => PostType::Formato,
        'title' => 'Solicitud de Préstamo Formato PDF',
    ]);

    $file = UploadedFile::fake()->create('formato-prestamo.pdf', 150, 'application/pdf');
    $media = $post->addMedia($file)->toMediaCollection('attachments');

    $response = $this->get('/publicaciones/adjuntos/'.$media->id);

    $response->assertStatus(200);
    $response->assertDownload('formato-prestamo.pdf');
});

test('navbar includes links to all post types', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertSee('/publicaciones/noticias');
    $response->assertSee('/publicaciones/avisos');
    $response->assertSee('/publicaciones/gestiones');
    $response->assertSee('/publicaciones/contratos');
    $response->assertSee('/publicaciones/formatos');
    $response->assertSee('/publicaciones/acervo');
});

test('legacy redirects point to new publication routes', function () {
    $this->get('/sindicato/recursos/formatos')
        ->assertRedirect('/publicaciones/formatos');

    $this->get('/sindicato/recursos/biblioteca')
        ->assertRedirect('/publicaciones/acervo');
});
