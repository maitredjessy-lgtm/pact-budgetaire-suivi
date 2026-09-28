<?php
/**
 * Lecture minimale d'un classeur Excel (.xlsx) sans bibliotheque externe :
 * renvoie les valeurs affichees (y compris le resultat des formules, tel qu'enregistre par Excel).
 */
class Xlsx
{
    private ZipArchive $zip;
    private array $chaines = [];
    private array $feuilles = []; // nom => chemin dans l'archive

    public function __construct(string $chemin)
    {
        $this->zip = new ZipArchive();
        if ($this->zip->open($chemin) !== true) {
            throw new RuntimeException('Fichier Excel illisible (.xlsx attendu)');
        }
        if (($x = $this->zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $d = new DOMDocument();
            $d->loadXML($x, LIBXML_NONET | LIBXML_COMPACT);
            foreach ($d->getElementsByTagName('si') as $si) {
                $t = '';
                foreach ($si->getElementsByTagName('t') as $n) {
                    $t .= $n->nodeValue;
                }
                $this->chaines[] = $t;
            }
        }
        $cibles = [];
        $rels = new DOMDocument();
        $rels->loadXML($this->zip->getFromName('xl/_rels/workbook.xml.rels'));
        foreach ($rels->getElementsByTagName('Relationship') as $r) {
            $cibles[$r->getAttribute('Id')] = $r->getAttribute('Target');
        }
        $wb = new DOMDocument();
        $wb->loadXML($this->zip->getFromName('xl/workbook.xml'));
        foreach ($wb->getElementsByTagName('sheet') as $s) {
            $rid = $s->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
            $cible = $cibles[$rid] ?? '';
            $this->feuilles[$s->getAttribute('name')] = str_starts_with($cible, '/') ? ltrim($cible, '/') : 'xl/' . $cible;
        }
    }

    public function nomsFeuilles(): array
    {
        return array_keys($this->feuilles);
    }

    /** Trouve une feuille dont le nom contient $fragment (insensible a la casse) */
    public function feuilleContenant(string $fragment): ?string
    {
        foreach ($this->nomsFeuilles() as $n) {
            if (stripos($n, $fragment) !== false) {
                return $n;
            }
        }
        return null;
    }

    /** @return array<int, array<string, string>> numero de ligne => [colonne => valeur] */
    public function lignes(string $feuille): array
    {
        $xml = $this->zip->getFromName($this->feuilles[$feuille] ?? '') ?: throw new RuntimeException("Feuille « {$feuille} » introuvable");
        $d = new DOMDocument();
        $d->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);
        $out = [];
        foreach ($d->getElementsByTagName('row') as $row) {
            $n = (int) $row->getAttribute('r');
            foreach ($row->getElementsByTagName('c') as $c) {
                $type = $c->getAttribute('t');
                if ($type === 'inlineStr') {
                    $v = $c->textContent;
                } else {
                    $v = $c->getElementsByTagName('v')->item(0)?->nodeValue;
                    if ($v !== null && $type === 's') {
                        $v = $this->chaines[(int) $v] ?? '';
                    }
                }
                if ($v === null || $v === '') {
                    continue;
                }
                $out[$n][preg_replace('/\d+/', '', $c->getAttribute('r'))] = $v;
            }
        }
        return $out;
    }
}
