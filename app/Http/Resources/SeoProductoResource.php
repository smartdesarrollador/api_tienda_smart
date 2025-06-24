<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeoProductoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'producto_id' => $this->producto_id,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'canonical_url' => $this->canonical_url,
            'schema_markup' => $this->schema_markup,
            'og_title' => $this->og_title,
            'og_description' => $this->og_description,
            'og_image' => $this->og_image,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // URLs completas
            'canonical_url_completa' => $this->getCanonicalUrlCompleta(),
            'og_image_url' => $this->og_image ? asset('assets/seo/' . $this->og_image) : null,

            // Información calculada
            'meta_title_length' => $this->meta_title ? strlen($this->meta_title) : 0,
            'meta_description_length' => $this->meta_description ? strlen($this->meta_description) : 0,
            'keywords_array' => $this->getKeywordsArray(),
            'keywords_count' => $this->getKeywordsCount(),

            // Validaciones SEO
            'validaciones_seo' => [
                'meta_title_valido' => $this->esMetaTitleValido(),
                'meta_description_valida' => $this->esMetaDescriptionValida(),
                'tiene_keywords' => $this->tieneKeywords(),
                'tiene_og_tags' => $this->tieneOgTags(),
                'tiene_canonical' => $this->tieneCanonical(),
                'schema_markup_valido' => $this->esSchemaMarkupValido(),
                'configuracion_completa' => $this->esConfiguracionCompleta(),
                'optimizado_basico' => $this->esOptimizadoBasico(),
            ],

            // Recomendaciones
            'recomendaciones' => $this->getRecomendaciones(),

            // Relaciones
            'producto' => $this->whenLoaded('producto', function () {
                return [
                    'id' => $this->producto->id,
                    'nombre' => $this->producto->nombre,
                    'slug' => $this->producto->slug ?? null,
                    'descripcion' => $this->producto->descripcion,
                    'precio' => (float) $this->producto->precio,
                    'activo' => (bool) $this->producto->activo,
                    'disponible' => (bool) $this->producto->disponible,
                ];
            }),

            // Datos de Open Graph estructurados
            'open_graph' => [
                'title' => $this->og_title ?: $this->meta_title,
                'description' => $this->og_description ?: $this->meta_description,
                'image' => $this->og_image ? asset('assets/seo/' . $this->og_image) : null,
                'url' => $this->getCanonicalUrlCompleta(),
                'type' => 'product',
            ],

            // Schema.org estructurado
            'schema_org' => $this->getSchemaOrgEstructurado(),
        ];
    }

    private function getCanonicalUrlCompleta(): ?string
    {
        if (!$this->canonical_url) {
            return null;
        }

        // Si ya es una URL completa, devolverla tal como está
        if (str_starts_with($this->canonical_url, 'http')) {
            return $this->canonical_url;
        }

        // Si es una ruta relativa, agregar el dominio
        return url($this->canonical_url);
    }

    private function getKeywordsArray(): array
    {
        if (!$this->meta_keywords) {
            return [];
        }

        return array_map('trim', explode(',', $this->meta_keywords));
    }

    private function getKeywordsCount(): int
    {
        return count($this->getKeywordsArray());
    }

    private function esMetaTitleValido(): bool
    {
        if (!$this->meta_title) {
            return false;
        }

        $length = strlen($this->meta_title);
        return $length >= 30 && $length <= 60; // Rango óptimo para títulos SEO
    }

    private function esMetaDescriptionValida(): bool
    {
        if (!$this->meta_description) {
            return false;
        }

        $length = strlen($this->meta_description);
        return $length >= 120 && $length <= 160; // Rango óptimo para descripciones SEO
    }

    private function tieneKeywords(): bool
    {
        return !empty($this->meta_keywords) && $this->getKeywordsCount() > 0;
    }

    private function tieneOgTags(): bool
    {
        return !empty($this->og_title) && !empty($this->og_description);
    }

    private function tieneCanonical(): bool
    {
        return !empty($this->canonical_url);
    }

    private function esSchemaMarkupValido(): bool
    {
        if (!$this->schema_markup) {
            return false;
        }

        // Validar que sea JSON válido
        if (is_string($this->schema_markup)) {
            $decoded = json_decode($this->schema_markup, true);
            return json_last_error() === JSON_ERROR_NONE && is_array($decoded);
        }

        return is_array($this->schema_markup);
    }

    private function esConfiguracionCompleta(): bool
    {
        return $this->esMetaTitleValido() && 
               $this->esMetaDescriptionValida() && 
               $this->tieneKeywords() && 
               $this->tieneOgTags() && 
               $this->tieneCanonical();
    }

    private function esOptimizadoBasico(): bool
    {
        return !empty($this->meta_title) && 
               !empty($this->meta_description) && 
               $this->tieneCanonical();
    }

    private function getRecomendaciones(): array
    {
        $recomendaciones = [];

        if (!$this->esMetaTitleValido()) {
            if (!$this->meta_title) {
                $recomendaciones[] = 'Agregar meta título para mejorar SEO';
            } else {
                $length = strlen($this->meta_title);
                if ($length < 30) {
                    $recomendaciones[] = 'El meta título es muy corto (mínimo 30 caracteres)';
                } elseif ($length > 60) {
                    $recomendaciones[] = 'El meta título es muy largo (máximo 60 caracteres)';
                }
            }
        }

        if (!$this->esMetaDescriptionValida()) {
            if (!$this->meta_description) {
                $recomendaciones[] = 'Agregar meta descripción para mejorar CTR';
            } else {
                $length = strlen($this->meta_description);
                if ($length < 120) {
                    $recomendaciones[] = 'La meta descripción es muy corta (mínimo 120 caracteres)';
                } elseif ($length > 160) {
                    $recomendaciones[] = 'La meta descripción es muy larga (máximo 160 caracteres)';
                }
            }
        }

        if (!$this->tieneKeywords()) {
            $recomendaciones[] = 'Agregar palabras clave relevantes al producto';
        }

        if (!$this->tieneOgTags()) {
            $recomendaciones[] = 'Configurar Open Graph tags para mejorar compartir en redes sociales';
        }

        if (!$this->tieneCanonical()) {
            $recomendaciones[] = 'Definir URL canónica para evitar contenido duplicado';
        }

        if (!$this->esSchemaMarkupValido()) {
            $recomendaciones[] = 'Implementar Schema.org markup para rich snippets';
        }

        if (empty($recomendaciones)) {
            $recomendaciones[] = 'Configuración SEO óptima ✓';
        }

        return $recomendaciones;
    }

    private function getSchemaOrgEstructurado(): ?array
    {
        if (!$this->esSchemaMarkupValido()) {
            return null;
        }

        $schema = is_string($this->schema_markup) 
            ? json_decode($this->schema_markup, true)
            : $this->schema_markup;

        // Asegurar estructura básica de producto
        if (!isset($schema['@type']) || $schema['@type'] !== 'Product') {
            $schema['@context'] = 'https://schema.org/';
            $schema['@type'] = 'Product';
        }

        return $schema;
    }
} 