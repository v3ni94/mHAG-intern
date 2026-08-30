{{--
    Gemeinsames PDF-Layout der Müller Holding AG (Abschnitt 132 Masterprompt).
    dompdf-tauglich, DIN A4, Schrift DejaVu Sans (Umlaute), CI "Goldpunkt".
    Verwendung: @extends('pdf.layout') mit @section('title') und @section('content').
    Optionale Variablen: $documentNumber (Dokumentnummer), $asOfDate (Stichtag).
--}}
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        @page {
            margin: 130px 56px 150px 56px;
        }

        * {
            box-sizing: border-box;
        }

        /* Achtung: kein margin-Reset auf html! dompdf interpretiert
           html-Margins als Seitenränder und überschreibt damit @page. */
        body {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            line-height: 1.5;
            color: #2E2D2E;
        }

        /* ---------- Kopfbereich (auf jeder Seite) ---------- */
        .pdf-header {
            position: fixed;
            top: -100px;
            left: 0;
            right: 0;
            height: 78px;
        }

        .pdf-header .head-table {
            width: 100%;
            border-collapse: collapse;
        }

        .pdf-header .head-table td {
            vertical-align: bottom;
            padding: 0;
        }

        .pdf-header .sender {
            font-size: 8px;
            color: #9F9F9F;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .pdf-header .logo-cell {
            text-align: right;
        }

        .pdf-header .logo-cell img {
            height: 52px;
        }

        /* Kopfnaht: Haarlinie mit Goldsegment */
        .pdf-header .seam {
            margin-top: 8px;
            border-bottom: 0.75px solid #DDDBD6;
            position: relative;
            height: 0;
        }

        .pdf-header .seam-gold {
            position: absolute;
            left: 0;
            top: -1px;
            width: 64px;
            height: 2.5px;
            background: #E3AC48;
        }

        /* ---------- Fußbereich (auf jeder Seite) ---------- */
        .pdf-footer {
            position: fixed;
            bottom: -128px;
            left: 0;
            right: 0;
        }

        .pdf-footer .meta-line {
            font-size: 7.5px;
            color: #9F9F9F;
            padding: 0 2px 4px 2px;
        }

        .pdf-footer .meta-line .pagenumber:before {
            content: counter(page);
        }

        .pdf-footer .footer-band {
            background: #2E2D2E;
            color: #FFFFFF;
            font-size: 7.5px;
            line-height: 1.7;
            text-align: center;
            padding: 12px 14px;
        }

        .pdf-footer .footer-band .line {
            white-space: nowrap;
        }

        /* ---------- Inhalt ---------- */
        .doc-title {
            font-size: 17px;
            font-weight: bold;
            color: #2E2D2E;
            margin: 0 0 4px 0;
        }

        .doc-title-goldbar {
            width: 44px;
            height: 3px;
            background: #E3AC48;
            margin: 0 0 12px 0;
        }

        .doc-meta {
            margin: 0 0 18px 0;
        }

        .versal-label {
            display: inline-block;
            font-size: 7.5px;
            color: #9F9F9F;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-right: 22px;
        }

        .versal-label .value {
            color: #2E2D2E;
            letter-spacing: 0.5px;
        }

        h1 { font-size: 15px; margin: 16px 0 6px 0; }
        h2 { font-size: 12px; margin: 14px 0 5px 0; }
        h3 { font-size: 10.5px; margin: 12px 0 4px 0; }

        p { margin: 0 0 8px 0; }

        table { border-collapse: collapse; }

        table.data {
            width: 100%;
            margin: 8px 0;
        }

        table.data th,
        table.data td {
            border-bottom: 0.75px solid #DDDBD6;
            padding: 4px 6px;
            text-align: left;
            font-size: 9.5px;
            vertical-align: top;
        }

        table.data th {
            font-size: 7.5px;
            color: #9F9F9F;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            border-bottom: 0.75px solid #9F9F9F;
        }

        table.data td.num,
        table.data th.num {
            text-align: right;
        }

        .text-end { text-align: right; }
        .text-muted { color: #9F9F9F; }
        .fw-bold { font-weight: bold; }

        .page-break { page-break-after: always; }

        @yield('pdf-styles')
    </style>
</head>
<body>

<div class="pdf-header">
    <table class="head-table">
        <tr>
            <td class="sender">Müller Holding AG</td>
            <td class="logo-cell">
                <img src="{{ public_path('images/logo-mhag.jpg') }}" alt="Müller Holding AG">
            </td>
        </tr>
    </table>
    <div class="seam"><div class="seam-gold"></div></div>
</div>

<div class="pdf-footer">
    <table style="width: 100%; border-collapse: collapse;" class="meta-line">
        <tr>
            <td style="text-align: left; font-size: 7.5px; color: #9F9F9F;">Erstellt am {{ format_date(now()) }}</td>
            <td style="text-align: right; font-size: 7.5px; color: #9F9F9F;">Seite <span class="pagenumber"></span></td>
        </tr>
    </table>
    <div class="footer-band">
        <div class="line">Müller Holding AG &middot; Rheinpromenade 13 &middot; 40789 Monheim am Rhein &middot; kontakt@mueller-holding.ag &middot; mueller-holding.ag</div>
        <div class="line">Sitz: Monheim am Rhein &middot; Registergericht: Amtsgericht Düsseldorf &middot; HRB 104291 &middot; Vorstand: Timo Müller &middot; Aufsichtsratsvorsitzender: Jan Walprecht</div>
    </div>
</div>

<main>
    @hasSection('title')
        <div class="doc-title">@yield('title')</div>
        <div class="doc-title-goldbar"></div>
    @endif

    @if (! empty($documentNumber) || ! empty($asOfDate))
        <div class="doc-meta">
            @if (! empty($documentNumber))
                <span class="versal-label">Dokumentnummer <span class="value">{{ $documentNumber }}</span></span>
            @endif
            @if (! empty($asOfDate))
                <span class="versal-label">Stichtag <span class="value">{{ format_date($asOfDate) }}</span></span>
            @endif
        </div>
    @endif

    @yield('content')
</main>

</body>
</html>
