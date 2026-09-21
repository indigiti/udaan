#!/usr/bin/env python3
import os, sys
HERE=os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(HERE,'vendor'))
import qrcode
import qrcode.image.svg

def main():
    if len(sys.argv)<2: raise SystemExit(2)
    url=sys.argv[1]
    qr=qrcode.QRCode(version=None,error_correction=qrcode.constants.ERROR_CORRECT_M,box_size=8,border=3)
    qr.add_data(url);qr.make(fit=True)
    img=qr.make_image(image_factory=qrcode.image.svg.SvgPathImage)
    img.save(sys.stdout.buffer)
if __name__=='__main__': main()
