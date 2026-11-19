<?php

namespace App\Services;

use Illuminate\Support\Collection;

class PolicySourceSnapshot
{
    public static function fromChunks(Collection $chunks): array
    {
        return $chunks->map(function ($chunk) {
            $doc = $chunk->document;
            return [
                'document_id' => $doc?->id,
                'chunk_id' => $chunk->id,
                'chunk_index' => $chunk->chunk_index,
                'file_hash' => $doc?->file_hash,
                'title' => $doc?->title ?? 'Unknown Document',
                'section' => $chunk->section_title,
                'page' => $chunk->page_number,
                'end_page' => $chunk->end_page_number,
                'excerpt' => mb_substr(trim((string) $chunk->content), 0, 4000, 'UTF-8'),
            ];
        })->values()->all();
    }

    public static function labels(array $snapshot): array
    {
        return collect($snapshot)->map(function (array $item) {
            $label = $item['title'];
            if (! empty($item['edition'])) { $label .= ' — ' . $item['edition']; }
            if (! empty($item['section'])) { $label .= ' — ' . $item['section']; }
            if (! empty($item['page'])) { $label .= ' — PDF p. ' . $item['page']; }
            return $label;
        })->unique()->values()->all();
    }

    public static function details(array $snapshot): array
    {
        return collect($snapshot)->map(function (array $item) {
            $label = self::labels([$item])[0];
            $url = null;
            if ($item['document_id'] && $item['file_hash']) {
                $url = route('hr.chat.policy.file', ['document' => $item['document_id'], 'hash' => $item['file_hash']]);
                if ($item['page']) { $url .= '#page=' . (int) $item['page']; }
            }
            return ['label' => $label, 'url' => $url, 'excerpt' => mb_substr($item['excerpt'], 0, 300, 'UTF-8')];
        })->all();
    }
}
