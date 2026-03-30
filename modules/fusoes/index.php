<?php
$pageTitle = 'Mapa de Fusões';
$activePage = 'fusoes';
require_once __DIR__ . '/../../includes/header.php';
$db = Database::getInstance();

$search = $_GET['q'] ?? '';
$sparams = $search ? ["%$search%", "%$search%"] : [];

$ceos = $db->fetchAll(
    "SELECT c.*,
        (SELECT COUNT(*) FROM fusoes f WHERE f.ceo_id = c.id) as total_fusoes,
        (SELECT COUNT(DISTINCT cp.cabo_id) FROM cabo_pontos cp WHERE cp.elemento_tipo='ceo' AND cp.elemento_id=c.id) as total_cabos
     FROM ceos c
     WHERE 1=1" . ($search ? " AND (c.codigo LIKE ? OR c.nome LIKE ?)" : "") . " ORDER BY c.codigo ASC",
    $sparams
);

$ctos = $db->fetchAll(
    "SELECT c.*,
        (SELECT COUNT(*) FROM fusoes f WHERE f.cto_id = c.id) as total_fusoes,
        (SELECT COUNT(DISTINCT cp.cabo_id) FROM cabo_pontos cp WHERE cp.elemento_tipo='cto' AND cp.elemento_id=c.id) as total_cabos
     FROM ctos c
     WHERE 1=1" . ($search ? " AND (c.codigo LIKE ? OR c.nome LIKE ?)" : "") . " ORDER BY c.codigo ASC",
    $sparams
);
?>
<div class="page-content">
    <div class="page-header">
        <div>
            <h2><i class="fas fa-sitemap" style="color:#ff9900"></i> Mapa de Fusões</h2>
            <p>CEOs e CTOs com cabos ancorados</p>
        </div>
        <div style="display:flex;gap:10px;align-items:center">
            <form style="display:flex;gap:8px">
                <input class="form-control" name="q" value="<?= e($search) ?>" placeholder="Buscar..." style="width:200px">
                <button class="btn btn-secondary" type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>
    </div>

    <!-- CEOs -->
    <h4 style="margin:0 0 12px;font-size:13px;text-transform:uppercase;letter-spacing:1px;color:#9933ff">
        <i class="fas fa-box"></i> Caixas de Emenda (CEO)
    </h4>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:32px">
        <?php if (empty($ceos)): ?>
        <div style="grid-column:1/-1;padding:30px;text-align:center;color:var(--text-muted);font-size:13px">Nenhuma CEO cadastrada</div>
        <?php endif; ?>
        <?php foreach ($ceos as $ceo):
            $pct = $ceo['capacidade_fo'] > 0 ? round($ceo['total_fusoes'] / $ceo['capacidade_fo'] * 100) : 0;
            $bc  = $pct >= 100 ? '#ff4455' : ($pct >= 80 ? '#ffaa00' : '#9933ff');
        ?>
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:20px;transition:border-color 0.2s"
             onmouseover="this.style.borderColor='rgba(153,51,255,0.5)'" onmouseout="this.style.borderColor='var(--border)'">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:10px">
                <div>
                    <div style="font-weight:700;font-size:15px"><?= e($ceo['codigo']) ?></div>
                    <?php if ($ceo['nome']): ?><div style="color:var(--text-muted);font-size:12px"><?= e($ceo['nome']) ?></div><?php endif; ?>
                </div>
                <?= formatStatus($ceo['status']) ?>
            </div>
            <div style="display:flex;gap:12px;font-size:12px;color:var(--text-muted);margin-bottom:10px">
                <span><i class="fas fa-layer-group" style="color:#9933ff"></i> <?= $ceo['capacidade_fo'] ?> FO</span>
                <span><i class="fas fa-sitemap" style="color:#ff9900"></i> <?= $ceo['total_fusoes'] ?> fusões</span>
                <span><i class="fas fa-minus" style="color:<?= $ceo['total_cabos']>0?'#3399ff':'#555' ?>"></i>
                    <span style="color:<?= $ceo['total_cabos']>0?'#3399ff':'#555' ?>"><?= $ceo['total_cabos'] ?> cabo(s)</span>
                </span>
            </div>
            <div style="background:rgba(255,255,255,0.08);border-radius:4px;height:5px;margin-bottom:14px">
                <div style="width:<?= min($pct,100) ?>%;height:100%;background:<?= $bc ?>;border-radius:4px"></div>
            </div>
            <div style="display:flex;gap:8px">
                <a href="<?= BASE_URL ?>/modules/fusoes/view.php?ceo_id=<?= $ceo['id'] ?>"
                   class="btn btn-primary" style="flex:1;text-align:center;font-size:13px">
                    <i class="fas fa-sitemap"></i> Ver Fusões
                </a>
                <a href="<?= BASE_URL ?>/modules/ceos/view.php?id=<?= $ceo['id'] ?>" class="btn btn-secondary" style="font-size:13px">
                    <i class="fas fa-eye"></i>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- CTOs -->
    <h4 style="margin:0 0 12px;font-size:13px;text-transform:uppercase;letter-spacing:1px;color:#00cc66">
        <i class="fas fa-box-open"></i> Caixas de Atendimento (CTO)
    </h4>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
        <?php if (empty($ctos)): ?>
        <div style="grid-column:1/-1;padding:30px;text-align:center;color:var(--text-muted);font-size:13px">Nenhuma CTO cadastrada</div>
        <?php endif; ?>
        <?php foreach ($ctos as $cto):
            $pct = $cto['capacidade_portas'] > 0 ? round($cto['total_fusoes'] / $cto['capacidade_portas'] * 100) : 0;
            $bc  = $pct >= 100 ? '#ff4455' : ($pct >= 80 ? '#ffaa00' : '#00cc66');
        ?>
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:20px;transition:border-color 0.2s"
             onmouseover="this.style.borderColor='rgba(0,204,102,0.5)'" onmouseout="this.style.borderColor='var(--border)'">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:10px">
                <div>
                    <div style="font-weight:700;font-size:15px"><?= e($cto['codigo']) ?></div>
                    <?php if ($cto['nome']): ?><div style="color:var(--text-muted);font-size:12px"><?= e($cto['nome']) ?></div><?php endif; ?>
                </div>
                <?= formatStatus($cto['status']) ?>
            </div>
            <div style="display:flex;gap:12px;font-size:12px;color:var(--text-muted);margin-bottom:10px">
                <span><i class="fas fa-plug" style="color:#00cc66"></i> <?= $cto['capacidade_portas'] ?> portas</span>
                <span><i class="fas fa-sitemap" style="color:#ff9900"></i> <?= $cto['total_fusoes'] ?> fusões</span>
                <span><i class="fas fa-minus" style="color:<?= $cto['total_cabos']>0?'#3399ff':'#555' ?>"></i>
                    <span style="color:<?= $cto['total_cabos']>0?'#3399ff':'#555' ?>"><?= $cto['total_cabos'] ?> cabo(s)</span>
                </span>
            </div>
            <div style="background:rgba(255,255,255,0.08);border-radius:4px;height:5px;margin-bottom:14px">
                <div style="width:<?= min($pct,100) ?>%;height:100%;background:<?= $bc ?>;border-radius:4px"></div>
            </div>
            <div style="display:flex;gap:8px">
                <a href="<?= BASE_URL ?>/modules/fusoes/view.php?cto_id=<?= $cto['id'] ?>"
                   class="btn btn-primary" style="flex:1;text-align:center;font-size:13px;background:rgba(0,204,102,0.2);border-color:rgba(0,204,102,0.4);color:#00cc66">
                    <i class="fas fa-sitemap"></i> Ver Fusões
                </a>
                <a href="<?= BASE_URL ?>/modules/ctos/view.php?id=<?= $cto['id'] ?>" class="btn btn-secondary" style="font-size:13px">
                    <i class="fas fa-eye"></i>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
