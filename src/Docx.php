<?php
/**
 * Import / export Word sans bibliotheque externe.
 *  - texteDepuisDocx() : extrait le texte (titres en Markdown) d'un .docx pour en faire un modele
 *  - depuisMarkdown()  : produit un vrai fichier .docx a partir du Markdown redige dans la plateforme
 * Necessite l'extension PHP "zip" (installee dans l'image Docker).
 */
class Docx
{
    public static function disponible(): bool
    {
        return class_exists('ZipArchive');
    }

    public static function texteDepuisDocx(string $chemin): string
    {
        $zip = new ZipArchive();
        $copie = null;
        if ($zip->open($chemin) !== true) {
            // Sous Windows, ZipArchive echoue sur certains noms accentues : on passe par une copie
            $copie = tempnam(sys_get_temp_dir(), 'docx');
            if (!copy($chemin, $copie) || $zip->open($copie) !== true) {
                @unlink($copie);
                throw new RuntimeException('Fichier .docx illisible');
            }
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($copie) {
            @unlink($copie);
        }
        if ($xml === false) {
            throw new RuntimeException('Ce fichier ne semble pas être un document Word (.docx)');
        }

        $dom = new DOMDocument();
        $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $xp->registerNamespace('mc', 'http://schemas.openxmlformats.org/markup-compatibility/2006');

        $lignes = [];
        foreach ($xp->query('//w:body/*') as $bloc) {
            if ($bloc->localName === 'tbl') {
                $premiere = true;
                foreach ($xp->query('.//w:tr', $bloc) as $tr) {
                    $cells = [];
                    foreach ($xp->query('./w:tc', $tr) as $tc) {
                        $paras = [];
                        foreach ($xp->query('.//w:p[not(ancestor::mc:Fallback)]', $tc) as $pc) {
                            $paras[] = trim(self::texteNoeud($xp, $pc, true));
                        }
                        $cells[] = str_replace('|', '/', implode(' ', array_filter($paras, 'strlen')));
                    }
                    $lignes[] = '| ' . implode(' | ', $cells) . ' |';
                    if ($premiere) {
                        $lignes[] = '|' . str_repeat('---|', count($cells));
                        $premiere = false;
                    }
                }
                $lignes[] = '';
                continue;
            }
            if ($bloc->localName !== 'p') {
                continue;
            }
            // Zones de texte (pages de garde, encadres) : une ligne par paragraphe interne
            foreach ($xp->query('.//w:txbxContent//w:p[not(ancestor::mc:Fallback)]', $bloc) as $pz) {
                $tz = trim(self::texteNoeud($xp, $pz));
                if ($tz !== '' && end($lignes) !== $tz) {
                    $lignes[] = $tz;
                    $lignes[] = '';
                }
            }
            $texte = trim(self::texteNoeud($xp, $bloc, true));
            if ($texte === '') {
                continue;
            }
            $style = $xp->query('./w:pPr/w:pStyle/@w:val', $bloc)->item(0);
            $prefixe = '';
            if ($style && preg_match('/(heading|titre)\s*(\d)/i', $style->nodeValue, $m)) {
                $prefixe = str_repeat('#', min(4, (int) $m[2])) . ' ';
            } elseif ($xp->query('./w:pPr/w:numPr', $bloc)->length > 0) {
                $prefixe = '- ';
            }
            $lignes[] = $prefixe . $texte;
            $lignes[] = '';
        }
        return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lignes)));
    }

    /** @param bool $horsZones exclut le texte des zones de texte (traite a part) */
    private static function texteNoeud(DOMXPath $xp, DOMNode $n, bool $horsZones = false): string
    {
        $out = '';
        // mc:Fallback duplique le contenu de mc:Choice (compatibilite anciennes versions de Word)
        $filtre = '[not(ancestor::mc:Fallback)' . ($horsZones ? ' and not(ancestor::w:txbxContent)' : '') . ']';
        foreach ($xp->query(".//w:t{$filtre}|.//w:tab{$filtre}|.//w:br{$filtre}", $n) as $t) {
            $out .= $t->localName === 't' ? $t->nodeValue : ' ';
        }
        return $out;
    }

    // ------------------------------------------------------------------ export

    public static function depuisMarkdown(string $markdown, string $titre): string
    {
        $corps = '';
        foreach (Markdown::blocs($markdown) as $b) {
            switch ($b['type']) {
                case 'titre':
                    $corps .= self::paragraphe($b['texte'], 'Heading' . min(4, $b['niveau']));
                    break;
                case 'puce':
                    $corps .= self::paragraphe('• ' . $b['texte'], null, 360);
                    break;
                case 'citation':
                    $corps .= self::paragraphe('*' . $b['texte'] . '*', 'Quote');
                    break;
                case 'tableau':
                    $corps .= self::tableau($b['lignes']);
                    break;
                case 'regle':
                    $corps .= '<w:p><w:pPr><w:pBdr><w:bottom w:val="single" w:sz="6" w:space="1" w:color="999999"/></w:pBdr></w:pPr></w:p>';
                    break;
                default:
                    foreach (explode("\n", $b['texte']) as $ligne) {
                        $corps .= self::paragraphe($ligne);
                    }
            }
        }

        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
            . $corps
            . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1418" w:right="1134" w:bottom="1418" w:left="1134" w:header="709" w:footer="709" w:gutter="0"/></w:sectPr>'
            . '</w:body></w:document>';

        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>');
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>' . htmlspecialchars($titre, ENT_XML1) . '</dc:title><dc:creator>Plateforme PACT Budgétaire</dc:creator></cp:coreProperties>');
        $zip->addFromString('word/styles.xml', self::styles());
        $zip->addFromString('word/document.xml', $document);
        $zip->close();
        $data = file_get_contents($tmp);
        unlink($tmp);
        return $data;
    }

    private static function runs(string $texte): string
    {
        $out = '';
        // Decoupe en segments **gras** / *italique* / texte simple
        foreach (preg_split('/(\*\*[^*]+\*\*|\*[^*\s][^*]*\*)/u', $texte, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $seg) {
            $rpr = '';
            if (preg_match('/^\*\*(.+)\*\*$/su', $seg, $m)) {
                $seg = $m[1];
                $rpr = '<w:rPr><w:b/></w:rPr>';
            } elseif (preg_match('/^\*(.+)\*$/su', $seg, $m)) {
                $seg = $m[1];
                $rpr = '<w:rPr><w:i/></w:rPr>';
            }
            $out .= '<w:r>' . $rpr . '<w:t xml:space="preserve">' . htmlspecialchars($seg, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</w:t></w:r>';
        }
        return $out;
    }

    private static function paragraphe(string $texte, ?string $style = null, int $retrait = 0): string
    {
        $ppr = '';
        if ($style || $retrait) {
            $ppr = '<w:pPr>' . ($style ? '<w:pStyle w:val="' . $style . '"/>' : '') . ($retrait ? '<w:ind w:left="' . $retrait . '"/>' : '') . '</w:pPr>';
        }
        return '<w:p>' . $ppr . self::runs($texte) . '</w:p>';
    }

    private static function tableau(array $lignes): string
    {
        $nbCol = max(array_map('count', $lignes));
        $largeur = (int) floor(9638 / max(1, $nbCol));
        $xml = '<w:tbl><w:tblPr><w:tblStyle w:val="TableGrid"/><w:tblW w:w="5000" w:type="pct"/>'
            . '<w:tblBorders><w:top w:val="single" w:sz="4" w:color="808080"/><w:left w:val="single" w:sz="4" w:color="808080"/><w:bottom w:val="single" w:sz="4" w:color="808080"/><w:right w:val="single" w:sz="4" w:color="808080"/><w:insideH w:val="single" w:sz="4" w:color="808080"/><w:insideV w:val="single" w:sz="4" w:color="808080"/></w:tblBorders></w:tblPr><w:tblGrid>'
            . str_repeat('<w:gridCol w:w="' . $largeur . '"/>', $nbCol) . '</w:tblGrid>';
        foreach ($lignes as $i => $cells) {
            $xml .= '<w:tr>';
            for ($c = 0; $c < $nbCol; $c++) {
                $txt = $cells[$c] ?? '';
                $shade = $i === 0 ? '<w:shd w:val="clear" w:color="auto" w:fill="1F4E79"/>' : '';
                $run = $i === 0
                    ? '<w:r><w:rPr><w:b/><w:color w:val="FFFFFF"/></w:rPr><w:t xml:space="preserve">' . htmlspecialchars(str_replace('**', '', $txt), ENT_XML1, 'UTF-8') . '</w:t></w:r>'
                    : self::runs($txt);
                $xml .= '<w:tc><w:tcPr><w:tcW w:w="' . $largeur . '" w:type="dxa"/>' . $shade . '</w:tcPr><w:p>' . $run . '</w:p></w:tc>';
            }
            $xml .= '</w:tr>';
        }
        return $xml . '</w:tbl><w:p/>';
    }

    private static function styles(): string
    {
        $titre = function (int $n, int $taille, string $couleur) {
            return '<w:style w:type="paragraph" w:styleId="Heading' . $n . '"><w:name w:val="heading ' . $n . '"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/>'
                . '<w:pPr><w:keepNext/><w:spacing w:before="240" w:after="120"/><w:outlineLvl w:val="' . ($n - 1) . '"/></w:pPr>'
                . '<w:rPr><w:b/><w:color w:val="' . $couleur . '"/><w:sz w:val="' . $taille . '"/></w:rPr></w:style>';
        };
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Calibri"/><w:sz w:val="22"/><w:lang w:val="fr-FR"/></w:rPr></w:rPrDefault>'
            . '<w:pPrDefault><w:pPr><w:spacing w:after="120" w:line="276" w:lineRule="auto"/><w:jc w:val="both"/></w:pPr></w:pPrDefault></w:docDefaults>'
            . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style>'
            . $titre(1, 32, '1F4E79') . $titre(2, 28, '1F4E79') . $titre(3, 24, '2E75B6') . $titre(4, 22, '2E75B6')
            . '<w:style w:type="paragraph" w:styleId="Quote"><w:name w:val="Quote"/><w:basedOn w:val="Normal"/><w:pPr><w:ind w:left="567" w:right="567"/><w:pBdr><w:left w:val="single" w:sz="18" w:space="8" w:color="E0A800"/></w:pBdr></w:pPr><w:rPr><w:color w:val="404040"/></w:rPr></w:style>'
            . '<w:style w:type="table" w:styleId="TableGrid"><w:name w:val="Table Grid"/><w:tblPr><w:tblCellMar><w:left w:w="100" w:type="dxa"/><w:right w:w="100" w:type="dxa"/></w:tblCellMar></w:tblPr></w:style>'
            . '</w:styles>';
    }
}

/** Mini-analyseur Markdown (titres, puces, tableaux, citations, paragraphes) */
class Markdown
{
    public static function blocs(string $md): array
    {
        $lignes = preg_split('/\r?\n/', $md);
        $blocs = [];
        $para = [];
        $flush = function () use (&$para, &$blocs) {
            if ($para) {
                $blocs[] = ['type' => 'paragraphe', 'texte' => implode("\n", $para)];
                $para = [];
            }
        };
        for ($i = 0; $i < count($lignes); $i++) {
            $l = rtrim($lignes[$i]);
            if (trim($l) === '') {
                $flush();
                continue;
            }
            if (preg_match('/^(#{1,6})\s+(.*)$/', $l, $m)) {
                $flush();
                $blocs[] = ['type' => 'titre', 'niveau' => strlen($m[1]), 'texte' => $m[2]];
            } elseif (preg_match('/^\s*[-*+]\s+(.*)$/', $l, $m)) {
                $flush();
                $blocs[] = ['type' => 'puce', 'texte' => $m[1]];
            } elseif (preg_match('/^>\s?(.*)$/', $l, $m)) {
                $flush();
                $blocs[] = ['type' => 'citation', 'texte' => $m[1]];
            } elseif (preg_match('/^(-{3,}|\*{3,})$/', trim($l))) {
                $flush();
                $blocs[] = ['type' => 'regle'];
            } elseif (str_starts_with(trim($l), '|')) {
                $flush();
                $rows = [];
                while ($i < count($lignes) && str_starts_with(trim($lignes[$i]), '|')) {
                    $row = trim(trim($lignes[$i]), '|');
                    if (!preg_match('/^[\s|:\-]+$/', $row)) {
                        $rows[] = array_map('trim', explode('|', $row));
                    }
                    $i++;
                }
                $i--;
                $blocs[] = ['type' => 'tableau', 'lignes' => $rows];
            } else {
                $para[] = $l;
            }
        }
        $flush();
        return $blocs;
    }
}
