<?php
/** Salt okunur bordro liste ölçümü. Uygulama kayıtlarına yazmaz. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/vendor/autoload.php';

final class BordroPerformanceStatement extends PDOStatement
{
    public static int $count = 0;
    public function execute(?array $params = null): bool
    {
        self::$count++;
        return parent::execute($params);
    }
}

try {
    $model = new App\Model\BordroPersonelModel();
    $model->db->setAttribute(PDO::ATTR_STATEMENT_CLASS, [BordroPerformanceStatement::class]);
    $stmt = $model->db->prepare('SELECT * FROM bordro_donemi WHERE silinme_tarihi IS NULL ORDER BY id');
    $stmt->execute();
    $periods = $stmt->fetchAll(PDO::FETCH_OBJ);
    $parametre = new App\Model\BordroParametreModel();
    $report = [];
    foreach ($periods as $period) {
        $_SESSION = ['firma_id'=>(int) $period->firma_id];
        $net = (float) $parametre->getGenelAyar('asgari_ucret_net', $period->baslangic_tarihi);
        BordroPerformanceStatement::$count = 0;
        $start = microtime(true);
        $rows = $model->getPersonellerByDonem($period->id);
        $listMs = (microtime(true) - $start) * 1000;
        $start = microtime(true);
        $values = [];
        foreach ($rows as $row) $values[$row->id] = $model->hesaplaOrtakGosterimDegerleri($row, $period, $net);
        $calcMs = (microtime(true) - $start) * 1000;
        $queryCount = BordroPerformanceStatement::$count;
        // Dönemler arasında kullanılan nesneyi yeni nesneyle karşılaştır.
        $fresh = new App\Model\BordroPersonelModel();
        $freshRows = $fresh->getPersonellerByDonem($period->id);
        $freshValues = [];
        foreach ($freshRows as $row) $freshValues[$row->id] = $fresh->hesaplaOrtakGosterimDegerleri($row, $period, $net);
        if ($values !== $freshValues) throw new RuntimeException('Dönem önbelleği tutarsız.');
        $report[] = ['period_id'=>(int) $period->id, 'rows'=>count($rows),
            'list_ms'=>round($listMs, 2), 'calculation_ms'=>round($calcMs, 2), 'queries'=>$queryCount,
            'fresh_model_match'=>true];
    }
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error) . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
