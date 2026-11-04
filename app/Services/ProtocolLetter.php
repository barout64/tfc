<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

/**
 * Protocol letters: the letterhead geometry (the same as public/protocol-app/render.js, which draws the letter on
 * screen and for printing) and the Word file. A letter's layout must agree in both, so change them together.
 */
class ProtocolLetter
{
    public const IMAGE_FILE = '/^[a-z0-9-]+?-(\d+)x(\d+)\.(png|jpg)$/';

    public const IMAGE_TAG = '/\[\[image:([a-z0-9-]+?-\d+x\d+\.(?:png|jpg))(?:\|(\d{1,4}))?(?:\|(left|center|right))?\]\]/';

    public const LOGO_POSITIONS = ['left', 'center', 'right'];

    public const HEADER_W = 642;

    public const MAX_LOGOS = 6;

    public const SYSTEM_FIELDS = ['ref' => 'Reference number', 'date' => 'Date', 'sender_name' => 'Signatory name', 'sender_title' => 'Signatory title', 'department' => 'Department', 'org_name' => 'Organization name'];

    private const MARGIN = 76;

    private const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    private const EMU_PER_PX = 9525;

    private const PLACEHOLDER = "\x02";

    public static function isImage(string $file): bool
    {
        return (bool) preg_match(self::IMAGE_FILE, $file);
    }

    public static function formatDate(mixed $v): string
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $v, $m) ? ((int) $m[3]).' '.self::MONTHS[(int) $m[2] - 1].' '.$m[1] : (string) $v;
    }

    /** @return list<string> */
    public static function fieldKeys(string $body): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $body, $m);

        return array_values(array_unique($m[1]));
    }

    /** @return list<string> */
    public static function imageKeys(string $body): array
    {
        preg_match_all(self::IMAGE_TAG, $body, $m);

        return array_values(array_unique($m[1]));
    }

    public static function logoWidth(array $l): int
    {
        if (! preg_match(self::IMAGE_FILE, (string) ($l['file'] ?? ''), $m)) {
            return 0;
        }

        return (int) max(10, min(640, round(($l['height'] ?? 84 ?: 84) * (int) $m[1] / (int) $m[2])));
    }

    public static function clampX(float $x, int $w): float
    {
        return round(max((-self::MARGIN / self::HEADER_W) * 100, min(((self::HEADER_W + self::MARGIN - $w) / self::HEADER_W) * 100, $x)) * 10) / 10;
    }

    /** Converts logos saved by earlier versions (left/center/right + offset) to free positions. */
    public static function normLogos(array $list): array
    {
        $old = array_filter($list, fn ($l) => ! isset($l['x']));
        $centerOld = collect($old)->firstWhere('position', 'center');
        $band = ($centerOld ? ($centerOld['height'] ?? 84) + 6 : 0) + 63;

        return array_map(function ($l) use ($band) {
            $height = ($l['height'] ?? 84) ?: 84;
            if (isset($l['x'])) {
                return ['file' => $l['file'], 'x' => (float) $l['x'], 'y' => (float) ($l['y'] ?? 0), 'height' => $height];
            }
            $w = self::logoWidth($l);
            $off = $l['offset'] ?? 0;
            $pos = $l['position'] ?? 'center';
            $left = $pos === 'left' ? $off : ($pos === 'right' ? self::HEADER_W - $off - $w : self::HEADER_W / 2 + $off - $w / 2);
            $y = $pos === 'center' ? 0 : max(0, round(($band - $height) / 2));

            return ['file' => $l['file'], 'x' => self::clampX(($left / self::HEADER_W) * 100, $w), 'y' => $y, 'height' => $height];
        }, array_values($list));
    }

    public static function freeImages(mixed $list): array
    {
        return array_values(array_filter(is_array($list) ? $list : [], fn ($b) => self::isImage((string) ($b['file'] ?? ''))));
    }

    private static function letterLogos(array $settings, mixed $logos): array
    {
        if (is_array($logos)) {
            return self::normLogos(array_values(array_filter($logos, fn ($l) => self::isImage((string) ($l['file'] ?? '')))));
        }
        if (empty($settings['logo'])) {
            return [];
        }
        $l = ['file' => $settings['logo'], 'height' => 84, 'y' => 0];
        $w = self::logoWidth($l);

        return [$l + ['x' => self::clampX(50 - ($w / self::HEADER_W) * 50, $w)]];
    }

    private static function headerLayout(array $logos, mixed $fixedTextTop): array
    {
        $textTop = $bandMin = 0;
        foreach ($logos as $l) {
            $h = ($l['height'] ?? 84) ?: 84;
            $left = ($l['x'] / 100) * self::HEADER_W;
            $right = $left + self::logoWidth($l);
            if ($right > self::HEADER_W * 0.3 && $left < self::HEADER_W * 0.7) {
                $textTop = max($textTop, $l['y'] + $h + 6);
            }
            $bandMin = max($bandMin, $l['y'] + $h);
        }

        return ['logos' => $logos, 'textTop' => $fixedTextTop ?? $textTop, 'bandMin' => $bandMin];
    }

    private static function xml(mixed $s): string
    {
        return str_replace(['&', '<', '>', '"'], ['&amp;', '&lt;', '&gt;', '&quot;'], preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', '', (string) $s));
    }

    private static function textRun(mixed $text, array $o = []): string
    {
        $props = (empty($o['bold']) ? '' : '<w:b/>').(empty($o['italic']) ? '' : '<w:i/>').(empty($o['caps']) ? '' : '<w:caps/>')
            .(empty($o['color']) ? '' : "<w:color w:val=\"{$o['color']}\"/>").(empty($o['size']) ? '' : "<w:sz w:val=\"{$o['size']}\"/>").(empty($o['highlight']) ? '' : '<w:highlight w:val="yellow"/>');

        return '<w:r>'.($props ? "<w:rPr>{$props}</w:rPr>" : '').'<w:t xml:space="preserve">'.self::xml($text).'</w:t></w:r>';
    }

    private static function para(string $runs, array $o = []): string
    {
        $exact = ! empty($o['exact']) ? " w:line=\"{$o['exact']}\" w:lineRule=\"exact\"" : '';
        $before = (int) round($o['before'] ?? 0);
        $after = $o['after'] ?? 180;
        $props = ($o['border'] ?? '').($o['indent'] ?? '')."<w:spacing w:before=\"{$before}\" w:after=\"{$after}\"{$exact}/>";
        if (! empty($o['align'])) {
            $props .= '<w:jc w:val="'.(in_array($o['align'], ['center', 'right'], true) ? $o['align'] : 'left').'"/>';
        }

        return "<w:p><w:pPr>{$props}</w:pPr>{$runs}</w:p>";
    }

    /** Each document part (body, footer) has its own image list; $prefix keeps their ids and file names apart. */
    private static function imagePart(callable $read, string $prefix = '', int $first = 0): object
    {
        return new class($read, $prefix, $first)
        {
            public array $media = [];

            public function __construct(private $read, private string $prefix, private int $pictures) {}

            /** $at = [x, y] in pixels places the image freely (floating, relative to the left margin and its paragraph). */
            public function image(string $file, mixed $width, mixed $height = null, ?array $at = null): string
            {
                if (! preg_match(ProtocolLetter::IMAGE_FILE, $file, $m) || ! ($data = ($this->read)($file))) {
                    return '';
                }
                $ratio = (int) $m[2] / (int) $m[1];
                $w = $width ? (int) $width : (int) $m[1];
                if ($height) {
                    $w = (int) round($height / $ratio);
                }
                $w = max(10, min($w, 640));
                $h = (int) round($w * $ratio);
                if (! isset($this->media[$file])) {
                    $n = count($this->media) + 1;
                    $this->media[$file] = ['rid' => "rIdImg{$this->prefix}{$n}", 'name' => strtolower($this->prefix)."image{$n}.".($m[3] === 'png' ? 'png' : 'jpeg'), 'data' => $data];
                }
                $entry = $this->media[$file];
                $n = ++$this->pictures;
                $cx = $w * 9525;
                $cy = $h * 9525;
                $open = $at
                    ? '<wp:anchor distT="0" distB="0" distL="0" distR="0" simplePos="0" relativeHeight="'.(251658240 + $n).'" behindDoc="0" locked="0" layoutInCell="1" allowOverlap="1">'
                        .'<wp:simplePos x="0" y="0"/><wp:positionH relativeFrom="margin"><wp:posOffset>'.round($at[0] * 9525).'</wp:posOffset></wp:positionH>'
                        .'<wp:positionV relativeFrom="paragraph"><wp:posOffset>'.round($at[1] * 9525)."</wp:posOffset></wp:positionV><wp:extent cx=\"{$cx}\" cy=\"{$cy}\"/><wp:effectExtent l=\"0\" t=\"0\" r=\"0\" b=\"0\"/><wp:wrapNone/>"
                    : "<wp:inline distT=\"0\" distB=\"0\" distL=\"0\" distR=\"0\"><wp:extent cx=\"{$cx}\" cy=\"{$cy}\"/>";

                return "<w:r><w:drawing>{$open}<wp:docPr id=\"{$n}\" name=\"Picture {$n}\"/>"
                    .'<wp:cNvGraphicFramePr><a:graphicFrameLocks noChangeAspect="1"/></wp:cNvGraphicFramePr><a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic>'
                    ."<pic:nvPicPr><pic:cNvPr id=\"{$n}\" name=\"{$entry['name']}\"/><pic:cNvPicPr/></pic:nvPicPr><pic:blipFill><a:blip r:embed=\"{$entry['rid']}\"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>"
                    ."<pic:spPr><a:xfrm><a:off x=\"0\" y=\"0\"/><a:ext cx=\"{$cx}\" cy=\"{$cy}\"/></a:xfrm><a:prstGeom prst=\"rect\"><a:avLst/></a:prstGeom></pic:spPr></pic:pic></a:graphicData></a:graphic></wp:".($at ? 'anchor' : 'inline').'></w:drawing></w:r>';
            }
        };
    }

    /** One line of body text: **bold**, [[image:...]] and highlighted placeholders. */
    private static function lineRuns(string $line, object $doc): string
    {
        $p = self::PLACEHOLDER;
        $out = '';
        $last = 0;
        preg_match_all('/\*\*(.+?)\*\*|\[\[image:([a-z0-9-]+?-\d+x\d+\.(?:png|jpg))(?:\|(\d{1,4}))?(?:\|(?:left|center|right))?\]\]|'.$p.'([^'.$p.']*)'.$p.'/', $line, $all, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL);
        foreach ($all as $m) {
            if ($m[0][1] > $last) {
                $out .= self::textRun(substr($line, $last, $m[0][1] - $last));
            }
            if ($m[1][0] !== null) {
                $out .= preg_replace_callback('/<w:r>(<w:rPr>)?/', fn ($r) => ! empty($r[1]) ? '<w:r><w:rPr><w:b/>' : '<w:r><w:rPr><w:b/></w:rPr>', self::lineRuns($m[1][0], $doc));
            } elseif ($m[2][0] !== null) {
                $out .= $doc->image($m[2][0], $m[3][0]);
            } else {
                $out .= self::textRun($m[4][0], ['highlight' => true]);
            }
            $last = $m[0][1] + strlen($m[0][0]);
        }

        return $out.($last < strlen($line) ? self::textRun(substr($line, $last)) : '');
    }

    private static function bodyXml(string $text, object $doc): string
    {
        $out = '';
        foreach (preg_split('/\n{2,}/', $text) as $p) {
            $trimmed = trim($p);
            if ($trimmed === '') {
                continue;
            }
            if (trim(preg_replace(self::IMAGE_TAG, '', $trimmed)) === '') {
                // Image row: images side by side, aligned like the first one
                preg_match_all(self::IMAGE_TAG, $trimmed, $tags, PREG_SET_ORDER);
                $out .= self::para(implode(self::textRun("\u{2003}"), array_map(fn ($m) => $doc->image($m[1], $m[2] ?? null), $tags)), ['align' => ($tags[0][3] ?? '') ?: 'left']);

                continue;
            }
            $lines = explode("\n", $p);
            $every = fn (string $re) => collect($lines)->every(fn ($l) => preg_match($re, $l));
            $bullets = $every('/^\s*(?:-|•)\s+/');
            $numbered = ! $bullets && $every('/^\s*\d+[.)]\s+/');
            if ($bullets || $numbered) {
                foreach ($lines as $i => $l) {
                    preg_match('/^\s*(\d+)/', $l, $num);
                    $marker = $bullets ? '•' : $num[1].'.';
                    $content = preg_replace($bullets ? '/^\s*(?:-|•)\s+/' : '/^\s*\d+[.)]\s+/', '', $l);
                    $out .= self::para(self::textRun($marker).'<w:r><w:tab/></w:r>'.self::lineRuns($content, $doc), ['indent' => '<w:ind w:left="720" w:hanging="360"/>', 'after' => $i === count($lines) - 1 ? 180 : 0]);
                }

                continue;
            }
            $out .= self::para(implode('<w:r><w:br/></w:r>', array_map(fn ($l) => self::lineRuns($l, $doc), $lines)));
        }

        return $out;
    }

    /** Puts field values into the template text. Missing values become highlighted [Label] placeholders when preview is on. */
    private static function fillBody(string $body, array $values, array $fields, bool $preview): string
    {
        $defs = array_column($fields, null, 'key');

        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($m) use ($defs, $values, $preview) {
            $k = $m[1];
            $d = $defs[$k] ?? null;
            $v = $values[$k] ?? null;
            if ($v !== null && trim((string) $v) !== '') {
                if ($k === 'date' || ($d['type'] ?? '') === 'date') {
                    $v = self::formatDate($v);
                }

                return preg_replace('/\n{2,}/', "\n", (string) $v);
            }

            return $preview ? self::PLACEHOLDER.'['.($d['label'] ?? self::SYSTEM_FIELDS[$k] ?? $k).']'.self::PLACEHOLDER : '';
        }, trim(str_replace("\r", '', $body)));
    }

    /** A real Word file for a letter, with the letterhead logo and the template's images. Returns the file's bytes. */
    public static function docx(array $o, callable $readImage): string
    {
        $s = $o['settings'] ?? [];
        $doc = self::imagePart($readImage);
        $parts = '';
        if (! empty($o['draftNotice'])) {
            $parts .= self::para(self::textRun($o['draftNotice'], ['bold' => true, 'color' => 'C00000', 'size' => 24]), ['align' => 'center', 'after' => 240]);
        }

        // Letterhead: logos float at their saved positions; the organization name starts below any logo above it
        $lay = self::headerLayout(self::letterLogos($s, $o['logos'] ?? null), $o['headerTextTop'] ?? null);
        $at = fn ($b) => [($b['x'] / 100) * self::HEADER_W, $b['y']];
        if ($lay['logos']) {
            $parts .= self::para(implode('', array_map(fn ($l) => $doc->image($l['file'], 0, $l['height'] ?? 84, $at($l)), $lay['logos'])), ['after' => 0, 'exact' => 20]);
        }
        $parts .= self::para(self::textRun($s['orgName'] ?? '', ['bold' => true, 'caps' => true, 'color' => '1F3A5F', 'size' => 36]), ['align' => 'center', 'after' => 0, 'before' => $lay['textTop'] * 15]);
        if (! empty($s['motto'])) {
            $parts .= self::para(self::textRun($s['motto'], ['italic' => true, 'size' => 20, 'color' => '444444']), ['align' => 'center', 'after' => 0]);
        }
        $department = $o['departmentName'] ?? '';
        if ($department) {
            $parts .= self::para(self::textRun($department, ['bold' => true, 'caps' => true, 'size' => 24]), ['align' => 'center', 'after' => 0]);
        }
        $gap = $lay['bandMin'] - $lay['textTop'] - (empty($s['orgName']) ? 0 : 30) - (empty($s['motto']) ? 0 : 17) - ($department ? 20 : 0);
        if ($gap > 0) {
            $parts .= self::para('', ['after' => 0, 'before' => $gap * 15, 'exact' => 20]);
        }
        // The double rule goes under the last line of the letterhead
        $rule = '<w:pBdr><w:bottom w:val="double" w:sz="6" w:space="6" w:color="1F3A5F"/></w:pBdr>';
        $banner = ! empty($o['headerImage']) && self::isImage((string) ($o['headerImage']['file'] ?? '')) ? $o['headerImage'] : null;
        $contact = implode('  |  ', array_filter([$s['address'] ?? '', $s['phone'] ?? '', $s['email'] ?? '']));
        $parts .= self::para($contact !== '' ? self::textRun($contact, ['size' => 18, 'color' => '333333']) : '', ['align' => 'center', 'after' => $banner ? 80 : 360, 'border' => $banner ? '' : $rule]);
        if ($banner) {
            $parts .= self::para($doc->image($banner['file'], 0, $banner['height'] ?? 60), ['align' => $banner['align'] ?? 'center', 'after' => 360, 'border' => $rule]);
        }

        // Free body images (signatures, stamps) float at their saved positions
        $free = self::freeImages($o['bodyImages'] ?? []);
        if ($free) {
            $parts .= self::para(implode('', array_map(fn ($b) => $doc->image($b['file'], 0, $b['height'] ?? 80, $at($b)), $free)), ['after' => 0, 'exact' => 20]);
        }
        $preview = ! empty($o['preview']);
        $parts .= self::bodyXml(self::fillBody((string) ($o['body'] ?? ''), $o['values'] ?? [], $o['fields'] ?? [], $preview), $doc);

        $foot = self::imagePart($readImage, 'F', 1000);
        $footImages = array_values(array_filter(is_array($o['footerImages'] ?? null) ? $o['footerImages'] : [], fn ($l) => self::isImage((string) ($l['file'] ?? ''))));
        $hasFooter = ! empty($s['footer']) || $footImages;
        $footCell = function (string $pos) use ($footImages, $foot) {
            $l = collect($footImages)->firstWhere('position', $pos);

            return '<w:tc><w:tcPr><w:tcW w:w="3212" w:type="dxa"/><w:vAlign w:val="center"/></w:tcPr>'.self::para($l ? $foot->image($l['file'], 0, $l['height'] ?? 50) : '', ['align' => $pos, 'after' => 0]).'</w:tc>';
        };
        $footRow = $footImages
            ? '<w:tbl><w:tblPr><w:tblW w:w="9638" w:type="dxa"/><w:tblLayout w:type="fixed"/><w:tblBorders><w:top w:val="nil"/><w:left w:val="nil"/><w:bottom w:val="nil"/><w:right w:val="nil"/><w:insideH w:val="nil"/><w:insideV w:val="nil"/></w:tblBorders>'
                .'<w:tblCellMar><w:left w:w="0" w:type="dxa"/><w:right w:w="0" w:type="dxa"/></w:tblCellMar></w:tblPr><w:tblGrid><w:gridCol w:w="3212"/><w:gridCol w:w="3214"/><w:gridCol w:w="3212"/></w:tblGrid>'
                .'<w:tr>'.$footCell('left').$footCell('center').$footCell('right').'</w:tr></w:tbl>'
            : '';

        $ns = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"';
        $head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $sect = '<w:sectPr>'.($hasFooter ? '<w:footerReference w:type="default" r:id="rIdFooter"/>' : '').'<w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" w:header="567" w:footer="567" w:gutter="0"/></w:sectPr>';
        $footRule = '<w:pBdr><w:top w:val="single" w:sz="4" w:space="4" w:color="999999"/></w:pBdr>';
        $footerXml = "{$head}<w:ftr {$ns}>".($footRow ? self::para('', ['after' => 0, 'border' => $footRule]).$footRow : '')
            .self::para(self::textRun($s['footer'] ?? '', ['size' => 17, 'color' => '444444']), ['align' => 'center', 'after' => 0, 'border' => $footRow ? '' : $footRule]).'</w:ftr>';
        $relType = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $imageRel = fn ($m) => "<Relationship Id=\"{$m['rid']}\" Type=\"{$relType}/image\" Target=\"media/{$m['name']}\"/>";
        $rels = "<Relationship Id=\"rIdStyles\" Type=\"{$relType}/styles\" Target=\"styles.xml\"/>".($hasFooter ? "<Relationship Id=\"rIdFooter\" Type=\"{$relType}/footer\" Target=\"footer1.xml\"/>" : '').implode('', array_map($imageRel, $doc->media));
        $relsNs = 'xmlns="http://schemas.openxmlformats.org/package/2006/relationships"';

        $files = [
            '[Content_Types].xml' => "{$head}<Types xmlns=\"http://schemas.openxmlformats.org/package/2006/content-types\"><Default Extension=\"rels\" ContentType=\"application/vnd.openxmlformats-package.relationships+xml\"/><Default Extension=\"xml\" ContentType=\"application/xml\"/>"
                .'<Default Extension="png" ContentType="image/png"/><Default Extension="jpeg" ContentType="image/jpeg"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
                .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'.($hasFooter ? '<Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>' : '').'</Types>',
            '_rels/.rels' => "{$head}<Relationships {$relsNs}><Relationship Id=\"rId1\" Type=\"{$relType}/officeDocument\" Target=\"word/document.xml\"/></Relationships>",
            'word/document.xml' => "{$head}<w:document {$ns}><w:body>{$parts}{$sect}</w:body></w:document>",
            'word/styles.xml' => "{$head}<w:styles xmlns:w=\"http://schemas.openxmlformats.org/wordprocessingml/2006/main\"><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii=\"Times New Roman\" w:hAnsi=\"Times New Roman\" w:eastAsia=\"Times New Roman\" w:cs=\"Times New Roman\"/><w:sz w:val=\"24\"/><w:szCs w:val=\"24\"/><w:lang w:val=\"en-GB\"/></w:rPr></w:rPrDefault>"
                .'<w:pPrDefault><w:pPr><w:spacing w:after="180" w:line="360" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults><w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style></w:styles>',
            'word/_rels/document.xml.rels' => "{$head}<Relationships {$relsNs}>{$rels}</Relationships>",
        ];
        if ($hasFooter) {
            $files['word/footer1.xml'] = $footerXml;
            if ($foot->media) {
                $files['word/_rels/footer1.xml.rels'] = "{$head}<Relationships {$relsNs}>".implode('', array_map($imageRel, $foot->media)).'</Relationships>';
            }
        }
        foreach ([...array_values($doc->media), ...array_values($foot->media)] as $m) {
            $files['word/media/'.$m['name']] = $m['data'];
        }

        $path = tempnam(sys_get_temp_dir(), 'tfcdoc');
        $zip = new ZipArchive;
        throw_unless($zip->open($path, ZipArchive::OVERWRITE) === true, RuntimeException::class, 'Could not create the Word file.');
        foreach ($files as $name => $data) {
            $zip->addFromString($name, $data);
        }
        $zip->close();
        $bytes = (string) file_get_contents($path);
        unlink($path);

        return $bytes;
    }
}
