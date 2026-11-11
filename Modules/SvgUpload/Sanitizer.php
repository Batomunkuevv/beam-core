<?php

namespace Beam\Core\Modules\SvgUpload;

defined('ABSPATH') || exit;

/**
 * Allowlist SVG sanitizer (no dependencies): keeps known shape/gradient/text elements and presentation attributes,
 * drops everything else — scripts, foreignObject, event handlers (on*), javascript:/data: and external links,
 * external url() in styles, DOCTYPE/entities (XXE), comments and processing instructions.
 */
final class Sanitizer
{
    private const ELEMENTS = [
        'svg', 'g', 'defs', 'symbol', 'use', 'title', 'desc', 'path', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'rect', 'text', 'tspan', 'textpath', 'lineargradient', 'radialgradient', 'stop', 'clippath', 'mask', 'pattern',
        'marker', 'filter', 'fegaussianblur', 'feoffset', 'feblend', 'fecolormatrix', 'fecomposite', 'feflood', 'femerge',
        'femergenode', 'femorphology', 'fedropshadow',
    ];

    private const ATTRIBUTES = [
        'id', 'class', 'style', 'xmlns', 'xmlns:xlink', 'version', 'viewbox', 'preserveaspectratio', 'width', 'height', 'x',
        'y', 'x1', 'x2', 'y1', 'y2', 'cx', 'cy', 'r', 'rx', 'ry', 'fx', 'fy', 'd', 'points', 'pathlength', 'transform',
        'fill', 'fill-opacity', 'fill-rule', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin',
        'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-opacity', 'opacity', 'color', 'display',
        'visibility', 'clip-path', 'clip-rule', 'clippathunits', 'mask', 'maskunits', 'maskcontentunits', 'filter',
        'filterunits', 'primitiveunits', 'offset', 'stop-color', 'stop-opacity', 'gradientunits', 'gradienttransform',
        'spreadmethod', 'patternunits', 'patterntransform', 'patterncontentunits', 'markerwidth', 'markerheight', 'refx',
        'refy', 'orient', 'markerunits', 'marker-start', 'marker-mid', 'marker-end', 'href', 'xlink:href', 'font-family',
        'font-size', 'font-weight', 'font-style', 'text-anchor', 'dominant-baseline', 'letter-spacing', 'dx', 'dy',
        'stddeviation', 'in', 'in2', 'result', 'mode', 'values', 'type', 'operator', 'k1', 'k2', 'k3', 'k4', 'radius',
        'flood-color', 'flood-opacity', 'vector-effect', 'shape-rendering', 'aria-hidden', 'aria-label', 'role',
        'focusable',
    ];

    /** Sanitized markup, or null when the input is not a parsable SVG. */
    public static function sanitize(string $svg): ?string
    {
        if (preg_match('/<!(DOCTYPE|ENTITY)/i', $svg) === 1) {
            return null;
        }

        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML(trim($svg), LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->documentElement;
        if (! $loaded || ! $root instanceof \DOMElement || strtolower($root->localName) !== 'svg') {
            return null;
        }

        self::clean($root);

        $out = $dom->saveXML($root);

        return is_string($out) && $out !== '' ? $out : null;
    }

    /** Width/height in px from the width/height attributes or the viewBox, [0, 0] when unknown. */
    public static function dimensions(string $svg): array
    {
        $attr = static fn(string $name): string => preg_match('/<svg\b[^>]*\s' . $name . '\s*=\s*["\']([^"\']*)["\']/i', $svg, $m) === 1 ? trim($m[1]) : '';
        $width = (float) $attr('width');
        $height = (float) $attr('height');
        $box = preg_split('/[\s,]+/', $attr('viewBox'));
        if (($width <= 0 || $height <= 0) && is_array($box) && count($box) === 4) {
            $width = (float) $box[2];
            $height = (float) $box[3];
        }

        return [max(0, (int) round($width)), max(0, (int) round($height))];
    }

    private static function clean(\DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMElement) {
                if (! in_array(strtolower($child->localName), self::ELEMENTS, true)) {
                    $node->removeChild($child);

                    continue;
                }
                self::attributes($child);
                self::clean($child);
            } elseif (! $child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                $node->removeChild($child); // comments, processing instructions, CDATA
            }
        }

        if ($node instanceof \DOMElement) {
            self::attributes($node);
        }
    }

    private static function attributes(\DOMElement $element): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            /** @var \DOMAttr $attribute */
            $name = strtolower($attribute->nodeName);
            $value = trim($attribute->value);
            $plain = strtolower((string) preg_replace('/[\s\x00-\x1f]+/', '', html_entity_decode($value)));

            $keep = in_array($name, self::ATTRIBUTES, true)
                && ! str_contains($plain, 'javascript:')
                && ! str_contains($plain, 'data:')
                && ! str_contains($plain, 'expression(');

            if ($keep && ($name === 'href' || $name === 'xlink:href')) {
                $keep = str_starts_with($value, '#'); // internal references only
            }
            if ($keep && str_contains($plain, 'url(')) {
                $keep = preg_match_all('/url\(\s*["\']?([^)"\']*)/i', $value, $m) > 0
                    && array_filter($m[1], static fn(string $url): bool => ! str_starts_with(trim($url), '#')) === [];
            }

            if (! $keep) {
                $element->removeAttributeNode($attribute);
            }
        }
    }
}
