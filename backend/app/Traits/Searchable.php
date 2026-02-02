<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait Searchable
{
    public function scopeSearch($query, $searchTerm, $searchableFields = [])
    {
        if (empty($searchTerm)) {
            return $query;
        }

        return $query->where(function ($q) use ($searchTerm, $searchableFields) {
            foreach ($searchableFields as $field) {
                $q->orWhere($field, 'like', "%{$searchTerm}%");
            }
        });
    }

    public function scopeSearchWithTypoTolerance($query, $searchTerm, $searchableFields = [], $tolerance = 2)
    {
        if (empty($searchTerm) || strlen($searchTerm) < 3) {
            return $this->scopeSearch($query, $searchTerm, $searchableFields);
        }

        // For short search terms, use regular search
        if (strlen($searchTerm) < 4) {
            return $this->scopeSearch($query, $searchTerm, $searchableFields);
        }

        // Implement typo tolerance using Levenshtein distance or soundex
        // This is a simplified implementation
        return $query->where(function ($q) use ($searchTerm, $searchableFields, $tolerance) {
            foreach ($searchableFields as $field) {
                // Regular search
                $q->orWhere($field, 'like', "%{$searchTerm}%");
                
                // Try variations for typo tolerance
                $variations = $this->generateTypoVariations($searchTerm, $tolerance);
                foreach ($variations as $variation) {
                    $q->orWhere($field, 'like', "%{$variation}%");
                }
            }
        });
    }

    protected function generateTypoVariations($term, $maxDistance = 2)
    {
        $variations = [];
        $length = strlen($term);

        // Character substitutions
        for ($i = 0; $i < $length; $i++) {
            $char = $term[$i];
            foreach (range('a', 'z') as $newChar) {
                if ($newChar !== $char) {
                    $variation = substr($term, 0, $i) . $newChar . substr($term, $i + 1);
                    $variations[] = $variation;
                }
            }
        }

        // Character insertions
        for ($i = 0; $i <= $length; $i++) {
            foreach (range('a', 'z') as $newChar) {
                $variation = substr($term, 0, $i) . $newChar . substr($term, $i);
                $variations[] = $variation;
            }
        }

        // Character deletions
        for ($i = 0; $i < $length; $i++) {
            $variation = substr($term, 0, $i) . substr($term, $i + 1);
            $variations[] = $variation;
        }

        // Transpositions
        for ($i = 0; $i < $length - 1; $i++) {
            $variation = $term;
            $temp = $variation[$i];
            $variation[$i] = $variation[$i + 1];
            $variation[$i + 1] = $temp;
            $variations[] = $variation;
        }

        return array_slice(array_unique($variations), 0, 50); // Limit variations
    }

    public function scopeFuzzySearch($query, $searchTerm, $searchableFields = [])
    {
        if (empty($searchTerm)) {
            return $query;
        }

        // Use MySQL FULLTEXT search if available
        if ($this->hasFullTextIndex()) {
            return $this->scopeFullTextSearch($query, $searchTerm, $searchableFields);
        }

        // Fallback to LIKE with soundex/metaphone
        return $query->where(function ($q) use ($searchTerm, $searchableFields) {
            foreach ($searchableFields as $field) {
                // Regular search
                $q->orWhere($field, 'like', "%{$searchTerm}%");
                
                // Soundex search for phonetic matching
                $soundex = soundex($searchTerm);
                if ($soundex !== false) {
                    $q->orWhereRaw("SOUNDEX({$field}) = ?", [$soundex]);
                }
            }
        });
    }

    protected function hasFullTextIndex()
    {
        // Check if table has FULLTEXT index
        $table = $this->getTable();
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Index_type = 'FULLTEXT'");
        return !empty($indexes);
    }

    protected function scopeFullTextSearch($query, $searchTerm, $searchableFields = [])
    {
        // Prepare search term for FULLTEXT search
        $searchTerm = preg_replace('/[^\p{L}\p{N}_]+/u', ' ', $searchTerm);
        $searchTerm = trim($searchTerm);
        $searchTerm = preg_replace('/\s+/', ' ', $searchTerm);
        
        // Convert to boolean mode search
        $words = explode(' ', $searchTerm);
        $booleanSearch = implode('* ', $words) . '*';

        // Build MATCH AGAINST clause
        $fields = implode(',', $searchableFields);
        
        return $query->whereRaw("MATCH({$fields}) AGAINST(? IN BOOLEAN MODE)", [$booleanSearch])
            ->orderByRaw("MATCH({$fields}) AGAINST(?) DESC", [$searchTerm]);
    }

    public function scopeSearchBySKU($query, $sku)
    {
        return $query->where('sku', 'like', "%{$sku}%");
    }

    public function scopeSearchByPartial($query, $field, $value)
    {
        return $query->where($field, 'like', "%{$value}%");
    }

    public function scopeSearchByMultiple($query, $filters)
    {
        foreach ($filters as $field => $value) {
            if (!empty($value)) {
                if (is_array($value)) {
                    $query->whereIn($field, $value);
                } else {
                    $query->where($field, 'like', "%{$value}%");
                }
            }
        }
        
        return $query;
    }
}