<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

final class SchoolPdfGenerator
{
    public function issuedDocument(array $document): string
    {
        $typeLabel = $document['tipo_documento'] === 'certificado'
            ? 'Certificado escolar'
            : 'Declaração escolar';
        $body = nl2br($this->escape($document['conteudo_documento']), false);
        $html = '<!doctype html><html lang="pt"><head><meta charset="UTF-8"><style>'
            . $this->baseStyles()
            . 'body{font-size:14px}.document{border:3px double #183b64;padding:38px;min-height:690px}'
            . '.issuer{text-align:center;line-height:1.55}.issuer strong{font-size:18px}'
            . '.emblem{display:block;width:74px;height:74px;margin:0 auto 10px;object-fit:contain}'
            . 'h1{text-align:center;font-size:23px;margin:42px 0 24px;text-transform:uppercase}'
            . '.body{font-size:16px;line-height:1.9;text-align:justify;min-height:170px}'
            . '.reference{margin-top:24px;color:#334155;font-size:11px}'
            . '.signatures{margin-top:70px;width:100%;border-collapse:collapse;text-align:center}'
            . '.signatures td{width:50%;padding:0 20px;vertical-align:bottom;height:65px}'
            . '.sign-line{border-top:1px solid #1e293b;padding-top:8px}'
            . '.verify{margin-top:28px;padding-top:10px;border-top:1px solid #cbd5e1;font-size:10px;color:#334155}'
            . '</style></head><body><main class="document">'
            . $this->coatOfArmsImage()
            . '<header class="issuer"><strong>Sistema de Gestão e Emissão de Certificados Escolares</strong><br>'
            . '<strong>' . $this->escape($document['escola_nome']) . '</strong><br>'
            . $this->escape($document['escola_distrito']) . ' — '
            . $this->escape($document['escola_provincia']) . '</header>'
            . '<h1>' . $this->escape($typeLabel) . '</h1>'
            . '<div class="body">' . $body . '</div>'
            . '<p class="reference">Aluno: <strong>' . $this->escape($document['aluno_nome'])
            . '</strong> (' . $this->escape($document['codigo_central']) . ')</p>'
            . '<p class="reference">Referência: ' . $this->escape($document['codigo'])
            . '<br>Emitido em: ' . $this->escape($document['emitido_em']) . '</p>'
            . '<table class="signatures"><tr><td><div class="sign-line">Director da Escola</div></td>'
            . '<td><div class="sign-line">Chefe da Secretaria</div></td></tr></table>'
            . '<div class="verify">Código de validação: <strong>'
            . $this->escape($document['codigo_validacao']) . '</strong></div>'
            . '</main></body></html>';

        return $this->render($html, 'portrait');
    }

    public function documentRequestReceipt(array $receipt): string
    {
        $typeLabel = $receipt['tipo_documento'] === 'certificado'
            ? 'Certificado escolar'
            : 'Declaração escolar';
        $statusLabels = [
            'pendente' => 'Pendente',
            'em_analise' => 'Em análise',
            'emitido' => 'Emitido',
            'rejeitado' => 'Rejeitado'
        ];
        $status = $statusLabels[$receipt['estado']] ?? 'Indisponível';
        $validationCode = trim((string) ($receipt['codigo_validacao'] ?? ''));
        $isIssued = $receipt['estado'] === 'emitido'
            && preg_match('/^[A-F0-9]{32}$/', $validationCode) === 1;
        if ($isIssued) {
            $validationPath = $receipt['verification_path']
                . '?codigo=' . rawurlencode($validationCode);
            $validationBlock = '<section class="validation">'
                . '<h2>Consulta de autenticidade</h2>'
                . '<p>Introduza este código na página de validação pública do SGECE:</p>'
                . '<div class="validation-code">' . $this->escape($validationCode) . '</div>'
                . '<p class="validation-path">' . $this->escape($validationPath) . '</p>'
                . '</section>';
        } else {
            $validationBlock = '<p class="notice">'
                . 'Este recibo comprova a submissão do pedido. A validação pública do documento '
                . 'ficará disponível depois da emissão.</p>';
        }

        $response = trim((string) ($receipt['resposta_admin'] ?? ''));
        $responseBlock = $response === ''
            ? ''
            : '<p class="response"><strong>Resposta administrativa:</strong><br>'
                . nl2br($this->escape($response), false) . '</p>';
        $issuedAt = empty($receipt['emitido_em'])
            ? '—'
            : $this->escape($receipt['emitido_em']);
        $completedAt = empty($receipt['concluido_em'])
            ? '—'
            : $this->escape($receipt['concluido_em']);
        $html = '<!doctype html><html lang="pt"><head><meta charset="UTF-8"><style>'
            . $this->baseStyles()
            . '@page{margin:22px}'
            . 'body{font-size:11px;color:#172033}'
            . '.receipt{border:2px solid #183b64;padding:22px}'
            . '.emblem{display:block;width:60px;height:60px;margin:0 auto 8px;object-fit:contain}'
            . '.issuer{text-align:center;line-height:1.45;border-bottom:1px solid #cbd5e1;padding-bottom:8px}'
            . '.issuer strong{font-size:12px}'
            . 'h1{text-align:center;color:#183b64;font-size:17px;margin:14px 0 4px}'
            . '.status{text-align:center;font-weight:bold;margin:0 0 12px;color:#334155}'
            . '.request-code{text-align:center;border:1px solid #cbd5e1;background:#f8fafc;'
            . 'padding:9px;font-size:13px;font-weight:bold;letter-spacing:.5px}'
            . '.details{width:100%;border-collapse:collapse;margin:12px 0}'
            . '.details td{border-bottom:1px solid #e2e8f0;padding:6px 4px;vertical-align:top}'
            . '.details td:first-child{width:34%;font-weight:bold;color:#183b64}'
            . '.reason,.response{line-height:1.5;margin:10px 0}'
            . '.validation{margin-top:14px;padding:12px;border:1px solid #16a34a;background:#f0fdf4}'
            . '.validation h2{font-size:12px;color:#166534;margin:0 0 6px}'
            . '.validation-code{text-align:center;font-size:14px;font-weight:bold;letter-spacing:1px;'
            . 'background:#fff;border:1px dashed #166534;padding:8px}'
            . '.validation-path{font-size:9px;overflow-wrap:anywhere;margin:8px 0 0}'
            . '.notice{margin-top:14px;padding:10px;background:#fffbeb;color:#92400e}'
            . '.footer{margin-top:14px;padding-top:8px;border-top:1px solid #cbd5e1;'
            . 'font-size:9px;color:#475569;text-align:center}'
            . '</style></head><body><main class="receipt">'
            . $this->coatOfArmsImage()
            . '<header class="issuer"><strong>RECIBO DO SGECE</strong><br>'
            . '<strong>' . $this->escape($receipt['escola_nome']) . '</strong><br>'
            . $this->escape($receipt['escola_distrito']) . ' — '
            . $this->escape($receipt['escola_provincia']) . '</header>'
            . '<h1>' . ($isIssued ? 'RECIBO DE EMISSÃO' : 'RECIBO DO PEDIDO') . '</h1>'
            . '<p class="status">' . $this->escape($status) . '</p>'
            . '<div class="request-code">Protocolo: '
            . $this->escape($receipt['request_code']) . '</div>'
            . '<table class="details"><tbody>'
            . '<tr><td>Aluno</td><td>' . $this->escape($receipt['aluno_nome']) . '</td></tr>'
            . '<tr><td>Código do aluno</td><td>'
            . $this->escape($receipt['codigo_central']) . '</td></tr>'
            . '<tr><td>Documento</td><td>' . $this->escape($typeLabel) . '</td></tr>'
            . '<tr><td>Pedido submetido</td><td>'
            . $this->escape($receipt['solicitado_em']) . '</td></tr>'
            . '<tr><td>Documento emitido</td><td>' . $issuedAt . '</td></tr>'
            . '<tr><td>Pedido concluído</td><td>' . $completedAt . '</td></tr>'
            . '</tbody></table>'
            . '<p class="reason"><strong>Motivo:</strong><br>'
            . nl2br($this->escape($receipt['motivo']), false) . '</p>'
            . $responseBlock
            . $validationBlock
            . '<p class="footer">Comprovativo gerado pelo SGECE. Guarde o protocolo para acompanhamento.'
            . '</p></main></body></html>';

        return $this->render($html, 'portrait');
    }

    public function subjectReport(array $report): string
    {
        $rows = '';
        $number = 0;
        foreach ($report['students'] as $student) {
            $number++;
            $rows .= '<tr><td class="student-number">' . $number . '</td>'
                . '<td class="student-code">' . $this->escape($student['codigo_central']) . '</td>'
                . '<td class="student-name">' . $this->escape($student['nome_completo']) . '</td>'
                . '<td class="score">' . $this->grade($student['acs1']) . '</td>'
                . '<td class="score">' . $this->grade($student['acs2']) . '</td>'
                . '<td class="score">' . $this->grade($student['avaliacao_provincial']) . '</td>'
                . '<td class="score average">' . $this->grade($student['media_trimestre']) . '</td></tr>';
        }
        $status = $report['completa'] ? 'PAUTA FINAL' : 'PAUTA PROVISÓRIA';
        $html = '<!doctype html><html lang="pt"><head><meta charset="UTF-8"><style>'
            . $this->gradeReportStyles()
            . '</style></head><body>'
            . $this->gradeReportHeader($report, 'PAUTA DA DISCIPLINA', $status)
            . '<table class="grades"><thead><tr>'
            . '<th class="student-number">N.º</th><th class="student-code">Código</th>'
            . '<th class="student-name">Nome do aluno</th><th class="score">ACS 1</th>'
            . '<th class="score">ACS 2</th><th class="score">Avaliação provincial</th>'
            . '<th class="score">Média trimestral</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>'
            . $this->gradeReportSignatures('Professor(a) da disciplina')
            . '<p class="foot">Emitida em '
            . $this->escape($report['issued_at'])
            . '. Média trimestral: média simples das duas ACS e da avaliação provincial; '
            . 'só é calculada quando as três avaliações estão preenchidas.</p>'
            . '</body></html>';

        return $this->render($html, 'landscape');
    }

    public function generalClassReport(array $report): string
    {
        $sections = '';
        foreach ($report['disciplines'] as $discipline) {
            $disciplineId = (int) $discipline['id'];
            $rows = '';
            $number = 0;
            foreach ($report['students'] as $student) {
                $number++;
                $grade = $student['grades'][$disciplineId] ?? [];
                $rows .= '<tr><td class="student-number">' . $number . '</td>'
                    . '<td class="student-code">'
                    . $this->escape($student['codigo_central'])
                    . '</td><td class="student-name">'
                    . $this->escape($student['nome_completo'])
                    . '</td><td class="score">' . $this->grade($grade['acs1'] ?? null)
                    . '</td><td class="score">' . $this->grade($grade['acs2'] ?? null)
                    . '</td><td class="score">'
                    . $this->grade($grade['avaliacao_provincial'] ?? null)
                    . '</td><td class="score average">'
                    . $this->grade($grade['nota'] ?? null) . '</td></tr>';
            }

            $sections .= '<section class="discipline-block"><h2 class="discipline-title">'
                . $this->escape($discipline['nome']) . '</h2>'
                . '<table class="grades"><thead><tr>'
                . '<th class="student-number">N.º</th><th class="student-code">Código</th>'
                . '<th class="student-name">Nome do aluno</th><th class="score">ACS 1</th>'
                . '<th class="score">ACS 2</th><th class="score">Avaliação provincial</th>'
                . '<th class="score">Média trimestral</th>'
                . '</tr></thead><tbody>' . $rows . '</tbody></table></section>';
        }

        $summaryRows = '';
        $number = 0;
        foreach ($report['students'] as $student) {
            $number++;
            $resultClass = $this->resultClass($student['resultado']);
            $summaryRows .= '<tr><td class="student-number">' . $number . '</td>'
                . '<td class="student-code">'
                . $this->escape($student['codigo_central'])
                . '</td><td class="student-name">'
                . $this->escape($student['nome_completo'])
                . '</td><td class="score average">'
                . $this->grade($student['media_final'])
                . '</td><td class="result ' . $resultClass . '">'
                . $this->escape($student['resultado']) . '</td>';
            $summaryRows .= '</tr>';
        }

        $status = $report['completa'] ? 'PAUTA FINAL' : 'PAUTA PROVISÓRIA';
        $html = '<!doctype html><html lang="pt"><head><meta charset="UTF-8"><style>'
            . $this->gradeReportStyles()
            . '</style></head><body>'
            . $this->gradeReportHeader($report, 'PAUTA GERAL DA TURMA', $status)
            . '<h2 class="section-title">Notas por disciplina</h2>'
            . $sections
            . '<section class="summary"><h2 class="section-title">Resultado final da turma</h2>'
            . '<table class="grades"><thead><tr>'
            . '<th class="student-number">N.º</th><th class="student-code">Código</th>'
            . '<th class="student-name">Nome do aluno</th>'
            . '<th class="score">Média final trimestral</th><th class="result">Resultado</th>'
            . '</tr></thead><tbody>' . $summaryRows . '</tbody></table></section>'
            . $this->gradeReportSignatures('Director de Turma')
            . '<p class="foot">Emitida em '
            . $this->escape($report['issued_at'])
            . '. A média trimestral é calculada pela média simples das duas ACS e da avaliação '
            . 'provincial, quando as três notas estão preenchidas. A média final é a média '
            . 'simples das médias de todas as disciplinas; a aprovação exige pelo menos '
            . $this->grade($report['passing_grade']) . ' valores.</p>'
            . '</body></html>';

        return $this->render($html, 'landscape');
    }

    public function annualClassReport(array $report): string
    {
        $sections = '';
        foreach ($report['disciplines'] as $discipline) {
            $disciplineId = (int) $discipline['id'];
            $rows = '';
            $number = 0;
            foreach ($report['students'] as $student) {
                $number++;
                $grade = $student['grades'][$disciplineId] ?? [];
                $rows .= '<tr><td class="student-number">' . $number . '</td>'
                    . '<td class="student-code">'
                    . $this->escape($student['codigo_central'])
                    . '</td><td class="student-name">'
                    . $this->escape($student['nome_completo']) . '</td>';
                for ($quarter = 1; $quarter <= 3; $quarter++) {
                    $quarterGrade = $grade[$quarter] ?? [];
                    $rows .= '<td class="score">' . $this->grade($quarterGrade['acs1'] ?? null)
                        . '</td><td class="score">'
                        . $this->grade($quarterGrade['acs2'] ?? null)
                        . '</td><td class="score">'
                        . $this->grade($quarterGrade['avaliacao_provincial'] ?? null)
                        . '</td><td class="score average">'
                        . $this->grade($quarterGrade['nota'] ?? null) . '</td>';
                }
                $rows .= '<td class="score annual-average">'
                    . $this->grade($grade['media_anual'] ?? null) . '</td></tr>';
            }

            $headers = '<tr><th class="student-number" rowspan="2">N.º</th>'
                . '<th class="student-code" rowspan="2">Código</th>'
                . '<th class="student-name" rowspan="2">Nome do aluno</th>';
            for ($quarter = 1; $quarter <= 3; $quarter++) {
                $headers .= '<th colspan="4">' . $quarter . '.º trimestre</th>';
            }
            $headers .= '<th class="annual-average" rowspan="2">Média anual</th></tr><tr>';
            for ($quarter = 1; $quarter <= 3; $quarter++) {
                $headers .= '<th>A1</th><th>A2</th><th>A3</th><th>Média</th>';
            }
            $headers .= '</tr>';
            $sections .= '<section class="discipline-block"><h2 class="discipline-title">'
                . $this->escape($discipline['nome']) . '</h2>'
                . '<table class="grades annual-table"><thead>' . $headers . '</thead>'
                . '<tbody>' . $rows . '</tbody></table></section>';
        }

        $summaryRows = '';
        $number = 0;
        foreach ($report['students'] as $student) {
            $number++;
            $resultClass = $this->resultClass($student['resultado']);
            $summaryRows .= '<tr><td class="student-number">' . $number . '</td>'
                . '<td class="student-code">'
                . $this->escape($student['codigo_central'])
                . '</td><td class="student-name">'
                . $this->escape($student['nome_completo'])
                . '</td><td class="score average">'
                . $this->grade($student['media_final'])
                . '</td><td class="result ' . $resultClass . '">'
                . $this->escape($student['resultado']) . '</td></tr>';
        }

        $status = $report['completa'] ? 'PAUTA ANUAL FINAL' : 'PAUTA ANUAL PROVISÓRIA';
        $html = '<!doctype html><html lang="pt"><head><meta charset="UTF-8"><style>'
            . $this->gradeReportStyles()
            . '</style></head><body>'
            . $this->gradeReportHeader($report, 'PAUTA ANUAL DA TURMA', $status)
            . '<h2 class="section-title">Notas por disciplina e trimestre</h2>'
            . $sections
            . '<section class="summary"><h2 class="section-title">Resultado final do ano</h2>'
            . '<table class="grades"><thead><tr>'
            . '<th class="student-number">N.º</th><th class="student-code">Código</th>'
            . '<th class="student-name">Nome do aluno</th>'
            . '<th class="score">Média final anual</th><th class="result">Resultado</th>'
            . '</tr></thead><tbody>' . $summaryRows . '</tbody></table></section>'
            . $this->gradeReportSignatures('Director de Turma')
            . '<p class="foot">Emitida em '
            . $this->escape($report['issued_at'])
            . '. A1, A2 e A3 são as avaliações do trimestre; M é a média trimestral. '
            . 'A média anual da disciplina é a média dos três trimestres. A média final '
            . 'anual é calculada quando todas as médias anuais estão disponíveis; a '
            . 'aprovação exige pelo menos ' . $this->grade($report['passing_grade'])
            . ' valores.</p>'
            . '</body></html>';

        return $this->render($html, 'landscape', 'A3');
    }

    private function gradeReportStyles(): string
    {
        return '@page{margin:24px}'
            . 'body{font-family:"DejaVu Sans",sans-serif;color:#172033;font-size:10px;line-height:1.35}'
            . '.school-heading{text-align:center;line-height:1.5;border-bottom:2px solid #183b64;padding-bottom:8px}'
            . '.school-heading strong{font-size:11px}'
            . 'h1{text-align:center;color:#183b64;font-size:16px;margin:12px 0 3px}'
            . '.status{text-align:center;font-size:9px;font-weight:bold;letter-spacing:.5px;margin:0 0 10px}'
            . '.status-final{color:#166534}.status-provisional{color:#9a6700}'
            . '.meta{width:100%;border-collapse:collapse;margin:8px 0 14px}'
            . '.meta td{width:50%;border:1px solid #cbd5e1;padding:5px 8px;background:#f8fafc}'
            . '.meta strong{color:#183b64}'
            . '.section-title{color:#183b64;font-size:12px;margin:12px 0 7px}'
            . '.discipline-block{margin:0 0 14px;page-break-inside:avoid}'
            . '.discipline-title{background:#e8eef5;color:#183b64;border-left:4px solid #183b64;'
            . 'padding:6px 8px;margin:0;font-size:11px;page-break-after:avoid}'
            . 'table.grades{width:100%;border-collapse:collapse;table-layout:fixed;font-size:9px}'
            . 'table.grades th,table.grades td{border:1px solid #94a3b8;padding:3px 3px}'
            . 'table.grades th{background:#183b64;color:#fff;text-align:center}'
            . 'table.grades thead{display:table-header-group}'
            . 'table.grades tr{page-break-inside:avoid}'
            . 'table.grades tbody tr:nth-child(even){background:#f8fafc}'
            . '.student-number{width:5%;text-align:center}'
            . '.student-code{width:13%;text-align:center;font-size:8px}'
            . '.student-name{width:37%;text-align:left}'
            . '.score{text-align:center;width:11.25%}'
            . '.average,.annual-average{font-weight:bold;background:#e8eef5}'
            . '.annual-table{font-size:8px!important}'
            . '.annual-table th,.annual-table td{padding:4px 2px!important}'
            . '.annual-table .student-number{width:3%}'
            . '.annual-table .student-code{width:9%}'
            . '.annual-table .student-name{width:18%}'
            . '.annual-table .score{width:4.7%}'
            . '.annual-table .annual-average{width:8%}'
            . '.result{text-align:center;font-weight:bold;width:18%}'
            . '.result-approved{color:#166534;background:#f0fdf4}'
            . '.result-failed{color:#b91c1c;background:#fef2f2}'
            . '.result-pending{color:#92400e;background:#fffbeb}'
            . '.summary{margin-top:16px;page-break-inside:avoid}'
            . '.signatures{width:100%;border-collapse:collapse;margin-top:32px;text-align:center;'
            . 'page-break-inside:avoid}'
            . '.signatures td{width:33.33%;padding:0 12px;vertical-align:bottom;height:48px}'
            . '.sign-line{border-top:1px solid #1e293b;padding-top:7px;font-size:9px}'
            . '.foot{margin-top:12px;font-size:8px;color:#475569}'
            . '.foot,.school-heading{line-height:1.5}';
    }

    private function gradeReportHeader(array $report, string $title, string $status): string
    {
        $metadata = [
            ['Classe', $report['class_name']],
            ['Turma', $report['classroom_name']],
            ['Ano lectivo', $report['year']],
            [
                'Período',
                isset($report['term'])
                    ? $report['term'] . '.º trimestre'
                    : 'Ano lectivo completo'
            ]
        ];
        if (isset($report['discipline_name'])) {
            $metadata[] = ['Disciplina', $report['discipline_name']];
        }
        $metadata[] = ['Número de alunos', (string) count($report['students'])];
        if (isset($report['disciplines'])) {
            $metadata[] = ['Número de disciplinas', (string) count($report['disciplines'])];
        }
        if (isset($report['passing_grade'])) {
            $metadata[] = [
                'Mínimo para aprovação',
                number_format((float) $report['passing_grade'], 2, '.', '')
            ];
        }

        $metadataRows = '';
        foreach (array_chunk($metadata, 2) as $fields) {
            $metadataRows .= '<tr>';
            foreach ($fields as [$label, $value]) {
                $metadataRows .= '<td><strong>' . $this->escape($label) . ':</strong> '
                    . $this->escape($value) . '</td>';
            }
            if (count($fields) === 1) {
                $metadataRows .= '<td></td>';
            }
            $metadataRows .= '</tr>';
        }

        $statusClass = $report['completa'] ? 'status-final' : 'status-provisional';
        return '<header class="school-heading"><strong>REPÚBLICA DE MOÇAMBIQUE</strong><br>'
            . '<strong>' . $this->escape($report['school_name']) . '</strong><br>'
            . $this->escape($report['school_district']) . '</header>'
            . '<h1>' . $this->escape($title) . '</h1>'
            . '<p class="status ' . $statusClass . '">' . $this->escape($status) . '</p>'
            . '<table class="meta"><tbody>' . $metadataRows . '</tbody></table>';
    }

    private function gradeReportSignatures(string $teacherTitle): string
    {
        return '<table class="signatures"><tr>'
            . '<td><div class="sign-line">Director da Escola</div></td>'
            . '<td><div class="sign-line">Chefe da Secretaria</div></td>'
            . '<td><div class="sign-line">' . $this->escape($teacherTitle) . '</div></td>'
            . '</tr></table>';
    }

    private function resultClass(string $result): string
    {
        return match ($result) {
            'Aprovado' => 'result-approved',
            'Reprovado' => 'result-failed',
            default => 'result-pending'
        };
    }

    private function coatOfArmsImage(): string
    {
        $imagePath = realpath(
            dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR . 'public'
            . DIRECTORY_SEPARATOR . 'assets'
            . DIRECTORY_SEPARATOR . 'img'
            . DIRECTORY_SEPARATOR . 'brasao-mocambique.png'
        );
        if ($imagePath === false) {
            throw new \RuntimeException('O brasão da República de Moçambique não foi encontrado.');
        }

        $image = file_get_contents($imagePath);
        if ($image === false) {
            throw new \RuntimeException('Não foi possível carregar o brasão da República de Moçambique.');
        }

        return '<img class="emblem" src="data:image/png;base64,'
            . base64_encode($image)
            . '" alt="Brasão da República de Moçambique">';
    }

    private function render(string $html, string $orientation, string $paper = 'A4'): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper($paper, $orientation);
        $pdf->render();

        return $pdf->output();
    }

    private function baseStyles(): string
    {
        return 'body{font-family:"DejaVu Sans",sans-serif;color:#172033;font-size:12px}'
            . 'header{text-align:center;line-height:1.5}';
    }

    private function grade($value): string
    {
        return $value === null ? '—' : $this->escape(number_format((float) $value, 2, '.', ''));
    }

    private function escape($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
