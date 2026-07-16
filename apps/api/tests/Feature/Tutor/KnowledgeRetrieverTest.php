<?php

declare(strict_types=1);

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\Tenant;
use App\Support\Tutor\KnowledgeRetriever;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->retriever = app(KnowledgeRetriever::class);
});

it('tokenizes a question, dropping stopwords and short tokens', function () {
    expect($this->retriever->tokenize('How does recursion work in Python?'))
        ->toBe(['recursion', 'work', 'python']);
});

it('ranks a dense short chunk above a sparse long one (length normalization)', function () {
    withinTenant($this->tenant, function () {
        $doc = KnowledgeDocument::factory()->for($this->tenant)->create(['is_active' => true]);

        // Dense: 'recursion' x3, only one unique term → high normalized score.
        KnowledgeChunk::factory()->for($this->tenant)->create([
            'document_id' => $doc->id, 'ordinal' => 0,
            'content' => 'recursion recursion recursion', 'term_count' => 1,
        ]);
        // Sparse: 'recursion' once inside a long, varied paragraph → low normalized score.
        KnowledgeChunk::factory()->for($this->tenant)->create([
            'document_id' => $doc->id, 'ordinal' => 1,
            'content' => 'recursion alpha beta gamma delta epsilon zeta eta theta iota kappa',
            'term_count' => 11,
        ]);
    });

    $hits = withinTenant($this->tenant, fn () => $this->retriever->retrieve(['recursion'], 5));

    expect($hits)->toHaveCount(2)
        ->and($hits->first()->ordinal)->toBe(0); // dense chunk wins
});

it('only retrieves chunks from active documents', function () {
    withinTenant($this->tenant, function () {
        $inactive = KnowledgeDocument::factory()->for($this->tenant)->create(['is_active' => false]);
        KnowledgeChunk::factory()->for($this->tenant)->create([
            'document_id' => $inactive->id, 'content' => 'recursion is powerful', 'term_count' => 3,
        ]);
    });

    $hits = withinTenant($this->tenant, fn () => $this->retriever->retrieve(['recursion'], 5));

    expect($hits)->toHaveCount(0);
});

it('returns nothing for an empty term set', function () {
    $hits = withinTenant($this->tenant, fn () => $this->retriever->retrieve([], 5));

    expect($hits)->toHaveCount(0);
});
