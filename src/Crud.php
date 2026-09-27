<?php
/**
 * CRUD generique pour les ressources simples (une table, une liste de champs autorises).
 */
class Crud
{
    public function __construct(
        private PDO $pdo,
        private string $table,
        private array $champs,          // champs modifiables
        private string $select,         // SELECT de base (avec jointures) ; alias de table "x"
        private string $ordre = 'x.id DESC',
        private array $defauts = []
    ) {
    }

    public function trouver(int $id): ?array
    {
        $stmt = $this->pdo->prepare($this->select . ' WHERE x.id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function lister(array $where = [], array $params = []): array
    {
        $sql = $this->select . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY ' . $this->ordre;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function valeurs(array $b, bool $creation): array
    {
        $v = [];
        foreach ($this->champs as $c) {
            if (array_key_exists($c, $b)) {
                $v[$c] = is_string($b[$c]) ? Response::orNull(trim($b[$c])) : $b[$c];
            } elseif ($creation && array_key_exists($c, $this->defauts)) {
                $v[$c] = $this->defauts[$c];
            }
        }
        return $v;
    }

    public function creer(array $b): array
    {
        $v = $this->valeurs($b, true);
        $cols = array_keys($v);
        $sql = "INSERT INTO {$this->table} (" . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
        $this->pdo->prepare($sql)->execute(array_values($v));
        return $this->trouver(Database::lastId($this->table));
    }

    public function modifier(int $id, array $b): ?array
    {
        $v = $this->valeurs($b, false);
        if ($v) {
            $set = implode(',', array_map(fn ($c) => "{$c} = ?", array_keys($v)));
            $aMaj = in_array($this->table, ['activites', 'documents', 'modeles', 'indicateurs'], true) ? ', updated_at = CURRENT_TIMESTAMP' : '';
            $this->pdo->prepare("UPDATE {$this->table} SET {$set}{$aMaj} WHERE id = ?")->execute([...array_values($v), $id]);
        }
        return $this->trouver($id);
    }

    public function supprimer(int $id): void
    {
        $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ?")->execute([$id]);
    }

    /** Traite la requete HTTP courante (GET liste/detail, POST, PUT, DELETE) */
    public function traiter(callable $filtres = null, callable $decorer = null): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
        $decorer ??= fn ($r) => $r;
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                if ($id) {
                    $r = $this->trouver($id);
                    $r ? Response::json($decorer($r)) : Response::error('Introuvable', 404);
                }
                [$where, $params] = $filtres ? $filtres() : [[], []];
                Response::json(array_map($decorer, $this->lister($where, $params)));
                // no break
            case 'POST':
                Auth::requireWrite();
                Response::json($decorer($this->creer(Response::body())), 201);
                // no break
            case 'PUT':
                Auth::requireWrite();
                if (!$id) {
                    Response::error('id requis', 422);
                }
                $r = $this->modifier($id, Response::body());
                $r ? Response::json($decorer($r)) : Response::error('Introuvable', 404);
                // no break
            case 'DELETE':
                Auth::requireWrite();
                if (!$id) {
                    Response::error('id requis', 422);
                }
                $this->supprimer($id);
                Response::json(['ok' => true]);
                // no break
            default:
                Response::error('Méthode non supportée', 405);
        }
    }
}
