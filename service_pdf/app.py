import os
import qrcode
from flask import Flask, request, jsonify, send_from_directory
from reportlab.lib import colors
from reportlab.lib.units import mm
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, Image
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle

app = Flask(__name__)
STORAGE_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), 'storage'))
os.makedirs(STORAGE_DIR, exist_ok=True)

@app.route('/health', methods=['GET'])
def health_check():
    return jsonify({
        "status": "healthy",
        "service": "Nairobi Traders Association (NTA-Pay) PDF Microservice",
        "storage_dir": STORAGE_DIR
    }), 200

@app.route('/generate-receipt', methods=['POST'])
def generate_receipt():
    data = request.get_json(silent=True)
    if not data:
        return jsonify({"error": "Invalid request payload"}), 400

    receipt_num = data.get('receipt_number', 'UNKNOWN')
    trader_name = data.get('trader_name', 'Counter Operator')
    customer_phone = data.get('customer_phone', data.get('customer_info', 'Walk-in Customer'))
    items = data.get('items', [])
    try:
        total_amount = float(data.get('total_amount', data.get('grand_total', 0.0)))
    except (ValueError, TypeError):
        total_amount = 0.0
    timestamp = data.get('timestamp', '')

    # 1. Generate High-Definition QR Code
    qr_filename = f"qr_{receipt_num}.png"
    qr_path = os.path.join(STORAGE_DIR, qr_filename)
    public_base_url = os.environ.get("PUBLIC_BASE_URL", "http://localhost:8000").rstrip("/")
    verify_url = f"{public_base_url}/verify.php?id={receipt_num}"

    qr = qrcode.QRCode(version=1, box_size=5, border=1)
    qr.add_data(verify_url)
    qr.make(fit=True)
    img_qr = qr.make_image(fill_color="black", back_color="white")
    img_qr.save(qr_path)

    # 2. Build 80mm Vector Thermal Receipt PDF
    pdf_filename = f"receipt_{receipt_num}.pdf"
    pdf_path = os.path.join(STORAGE_DIR, pdf_filename)

    PAGE_WIDTH = 80 * mm
    content_height = max(175, 115 + (len(items) * 8.5)) * mm

    doc = SimpleDocTemplate(
        pdf_path,
        pagesize=(PAGE_WIDTH, content_height),
        leftMargin=4 * mm,
        rightMargin=4 * mm,
        topMargin=6 * mm,
        bottomMargin=6 * mm
    )

    styles = getSampleStyleSheet()

    title_style = ParagraphStyle(
        'ReceiptTitle',
        parent=styles['Heading1'],
        fontName='Helvetica-Bold',
        fontSize=12,
        alignment=1,
        spaceAfter=2
    )

    center_sub = ParagraphStyle(
        'CenterSub',
        parent=styles['Normal'],
        fontName='Helvetica',
        fontSize=8,
        alignment=1,
        textColor=colors.HexColor("#475569")
    )

    body_style = ParagraphStyle(
        'ReceiptBody',
        parent=styles['Normal'],
        fontName='Helvetica',
        fontSize=8,
        leading=11
    )

    bold_style = ParagraphStyle(
        'ReceiptBold',
        parent=body_style,
        fontName='Helvetica-Bold'
    )

    elements = []

    # Header
    elements.append(Paragraph("NAIROBI TRADERS ASSN.", title_style))
    elements.append(Paragraph("Official Digital Proof of Purchase", center_sub))
    elements.append(Paragraph("Stall #42 &bull; POS Terminal #104", center_sub))
    elements.append(Spacer(1, 3 * mm))

    # Metadata
    meta_text = f"""
    <b>Receipt No:</b> {receipt_num}<br/>
    <b>Date:</b> {timestamp}<br/>
    <b>Served By:</b> {trader_name}<br/>
    <b>Customer:</b> {customer_phone}
    """
    elements.append(Paragraph(meta_text, body_style))
    elements.append(Spacer(1, 3 * mm))

    # Line Items Table
    table_data = [["Item", "Qty", "Price", "Total"]]
    for item in items:
        try:
            qty = item.get('quantity', 1)
            u_price = float(item.get('unit_price', 0))
            l_total = float(item.get('line_total', qty * u_price))
        except (ValueError, TypeError):
            qty = 1
            u_price = 0.0
            l_total = 0.0

        table_data.append([
            item.get('name', 'General Item')[:18],
            str(qty),
            f"{u_price:.0f}",
            f"{l_total:.0f}"
        ])

    t = Table(table_data, colWidths=[28 * mm, 10 * mm, 16 * mm, 18 * mm])
    t.setStyle(TableStyle([
        ('FONTNAME', (0, 0), (-1, -1), 'Helvetica'),
        ('FONTSIZE', (0, 0), (-1, -1), 8),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 2.5),
        ('TOPPADDING', (0, 0), (-1, -1), 2.5),
        ('LINEBELOW', (0, 0), (-1, 0), 0.5, colors.HexColor("#CBD5E1")),
        ('LINEBELOW', (0, -1), (-1, -1), 0.5, colors.HexColor("#CBD5E1")),
        ('ALIGN', (1, 0), (-1, -1), 'RIGHT'),
    ]))
    elements.append(t)
    elements.append(Spacer(1, 3 * mm))

    # Grand Total
    elements.append(Paragraph(f"<b>GRAND TOTAL: KES {total_amount:,.2f}</b>", bold_style))
    elements.append(Paragraph("<b>SETTLEMENT: M-PESA STK VERIFIED</b>", center_sub))
    elements.append(Spacer(1, 3 * mm))

    # Dynamic QR Code
    qr_img = Image(qr_path, width=28 * mm, height=28 * mm)
    qr_img.hAlign = 'CENTER'
    elements.append(qr_img)
    elements.append(Paragraph("Scan to verify authentic transaction", center_sub))
    elements.append(Paragraph("NTA-Pay &bull; Pay First, Receipt Comes", center_sub))

    doc.build(elements)

    return jsonify({
        "status": "success",
        "pdf_url": f"/storage/{pdf_filename}",
        "qr_url": f"/storage/{qr_filename}"
    }), 200

@app.route('/storage/<path:filename>')
def serve_storage(filename):
    return send_from_directory(STORAGE_DIR, filename)

if __name__ == '__main__':
    print(f"Starting NTA-Pay Microservice on http://0.0.0.0:5000 (Storage: {STORAGE_DIR})")
    app.run(host='0.0.0.0', port=5000, debug=False)
