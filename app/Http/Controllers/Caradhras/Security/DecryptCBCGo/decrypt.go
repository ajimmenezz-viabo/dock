package main

import (
	"crypto/aes"
	"crypto/cipher"
	"crypto/rand"
	"crypto/rsa"
	"crypto/sha256"
	"crypto/x509"
	"encoding/base64"
	"encoding/pem"
	"fmt"
)

func main() {
	// if len(os.Args) < 5 {
	// 	fmt.Println("Usage: <program> <pkBase64> <aesBase64> <ivBase64> <msgBase64>")
	// 	return
	// }

	// pkBase64 := os.Args[1]
	// aesBase64 := os.Args[2]
	// ivBase64 := os.Args[3]
	// msgBase64 := os.Args[4]

	pkBase64 := "LS0tLS1CRUdJTiBQUklWQVRFIEtFWS0tLS0tCk1JSUV2d0lCQURBTkJna3Foa2lHOXcwQkFRRUZBQVNDQktrd2dnU2xBZ0VBQW9JQkFRRElxRmhEZWlrdS9mN3YKV0tScUFFK1pRK0tiNGVsdERldkpjVTJiMk5paVI3R3FGNVNlZnVRTGtHYVY5d3BpU1NRT1dzUTBwakJaNzEwTQpIZGVYLzdqVFJxc3IyaXA2NWlPekRSQUFFOTlhblRLZElrang0RW01bUVKWjRNM3M3RnlOTW44OC9mSlgrWHBsCmErTkFmZkVReWZxa3hvSVBhQXZGd2xsTnU1RUtWdG5VNEY5YkhMdENvZFhIQzdsT25Lc1gzWHBLM09VaG9CVDkKM0ZEUkVHK2RJZ2ZHdS9yUzBvamhycGVxbG05QUJtck5nTlI1WXk5ZGdnall3N001MWZTQjZleFV0dEVuUGVzMQpJcDI2MlVhM3ZsVVVDUjl6OGIzbytQSm1Tb2ZrcWNESVFDNm9qcnh2UEVMN3Z2bGR1S1JRc3BDWDZzL1hFaEVhClRxeGdOZVp4QWdNQkFBRUNnZ0VBTXBWRmt0VVQxclhPODNWTUZUQzQ0REVkeWlBY0lSSzJVdFRPTkxCb2hCaEEKc0ZrN2JPMGQvZEJNSEJmbnRUa3M3clZ3NnJqT1RZMnF6aWdqdGp5UDBpcnBjYWVRdCtTV01VZmt0YkJNeU9JQgo1VnpFT0wxS3VJK3Fnay9LZWFSbi9Hd3phU08zV1BnYUk4RWJ5NkUwQ1FCeHYrSU8zV1ZrT2xrdy9BaUJtckl4CktUbkFjU3gxSGZETU5vbVdDZXdvTlJyc3U4QWUzUkt2SC9IRzNyYUc3L0wxbEV4OURGRk5rQnIzRFY3YnBpREQKdGZhWTVPcGNvUlQyaE0wK3IvYWxiVm1NZDZBakpBNGt0UXFBNnpaZ2F6ODhUNWJ4R0w4S3dDdU44cENTbXFuMAo3bWZjUVVwYWlDZGxtN1hnWGt5SU81NFFZS2w1UGZZcmVaSGlrdWJnY1FLQmdRRC9STll6T29EUSs3WlFCcUw2CnViTjBSRjJvZW5EYlhoV3FtbXJCbGZJRTNNNUY3dExlQUdHSzFPcDJkOFFZYTRQMUNRVjJZSGNxekZOUmorcVgKSFhXbHF2MkZNTnQraC9PUnd6ekFXQXJ6ZG1OMDUzNGZ4Mk42V0JRb3UrbDRITTAzaC9aeUozZS9kcWFEbDh3Sgp3MmoyR3hWRG5TaEs2amJTUnpRT1pKYlpYUUtCZ1FESk8zZVBUSjRjYWdERHFvaGdrQ0tuV1pSR3E1TisyQ1FnCmp2akdoc3JYRHBTb3pIMUtQZjFlQ2FpcldiOWZXbEpkWGMzdDd2akxDb3lNQm1SUUxCbFJQY3hJb0hvamx6S0oKWHBKa08wbThqWFZaUmdOM0RrQ1Q0Q0h5UnZmSWQ1SXFFcFJxQVJhT21zN0dSTXV2VHp6SWlLcFZHbmMrY2Z5ZApwU3VCUWZhc0pRS0JnUUMxc3hEcERCYVBLdXg5a0F2SWZoQnZqUTVCazAzcng4K1NUVEg2TTdvK25kRXgxQ3BDCm5YRFErbmNkOW1nZG5jSWkwOVlRaWQrcEZpR2taOVZxMVN4ZHpSV0NEZUhlOGZSODU4VEJnS25pM0gwMHhHWngKUm1MWHZnUXpiblpqNmRSbFY2RWpabGFGY1haYkt0eXdnbWllN1c0NFg1QkRxdTEweGZ3VzFxRE5mUUtCZ1FESAptdHdTWVc1b1F1RjFOTC9JQU5ETzdQVStVRXlpd21TN2d2WFRmcnJQTFdCYU8xU0FBeE5DWnhST3Uxd3ZpaGt2CkViQUZ0a2hFcHJjWTRmSTQ4RFZBdDZyZDA0aXpxdk51L1ViNmN6REYvZzhMdVg0UVp5dTVRdGFKU3NuWHFIdHMKampkM0dwTTBhdXgyRUtGMXlJUkRhZ1NESEJoeDhZRWhJa2dRRTgxSm5RS0JnUURQN2x4ZHFuL1RMeDJCVHlRVgpjQzdxcXNES21rUit3a0t4VGlIK2I0Z1U3NWtmLy9JYlV1aTVtd01GTDhqSDIxclpXd2JjWWZNM1FQMU5RZlE5CldBZ0ZsM1J0NHNaQ1dydFFOTHpVQ2FRcHVuaXlsV2pvNDdoNVo2YnlJbDFlNHJqYXFyTU9XQUJ0MWF3dzY3RjQKUGNVbTdhM1J6WnJyV1J0OGkwUnBVVVVkVlE9PQotLS0tLUVORCBQUklWQVRFIEtFWS0tLS0tCg=="
	aesBase64 := "u8+f0dbxv9gC+EOuJWk8VQDmVGmkdXSddXWlFZWP5qH0m1VsBhs+dYnkR0Cgg5HZDD1i5HjB9nBdQT8RURvYVNyfFulXgEpf0ZWTscNHfoPHOQTsG+fgi3AYwUvbkAoMrxarh3Bwxllp22MjxFy9xqUVRLZK7Ue9jGYB4LruVHoBz4D5gAG9Cr4VcDnIIbtOhBNxtxE8WisgTxpQNpMhYk1f7pdol/TxWW+1cgRRPfagQo/iyiohDMyxP53f4+L04se1B6UoO+vVcr9JK+8w7WpZ2gIfClOfj+vQTxQobY/E9JJhMvhaA3AuVYrT/TupA71pb4wGVN6pSoHYeCdojQ=="
	ivBase64 := "WUpCMk52VE41ZldXcUxCUA=="
	msgBase64 := "QUdfTtsFCq6fJyGzTHhGoQ=="

	// Decodificar la clave privada en formato base64
	pk, err := base64.StdEncoding.DecodeString(pkBase64)
	if err != nil {
		panic(err)
	}

	// Decodificar el bloque PEM
	blockPEM, _ := pem.Decode(pk)
	if blockPEM == nil {
		panic("Failed to decode PEM block containing the key")
	}

	// Parsear la clave privada
	privateKey, err := x509.ParsePKCS8PrivateKey(blockPEM.Bytes)
	if err != nil {
		panic(err)
	}

	pkPEM := privateKey.(*rsa.PrivateKey)

	// Decodificar la clave AES en formato base64
	aesKey, err := base64.StdEncoding.DecodeString(aesBase64)
	if err != nil {
		panic(err)
	}

	// Desencriptar la clave AES usando RSA-OAEP
	hash := sha256.New()
	key, err := rsa.DecryptOAEP(hash, rand.Reader, pkPEM, aesKey, nil)
	if err != nil {
		panic(err)
	}

	// Decodificar el IV en formato base64
	iv, err := base64.StdEncoding.DecodeString(ivBase64)
	if err != nil {
		panic(err)
	}

	if len(iv) != aes.BlockSize {
		panic(fmt.Sprintf("Incorrect IV length: expected %d bytes, got %d", aes.BlockSize, len(iv)))
	}

	// Decodificar el mensaje en formato base64
	msg, err := base64.StdEncoding.DecodeString(msgBase64)
	if err != nil {
		panic(err)
	}

	// Crear el cifrador AES
	block, err := aes.NewCipher(key)
	if err != nil {
		panic(err)
	}

	// Crear el modo CBC de descifrado
	mode := cipher.NewCBCDecrypter(block, iv)

	// Crear el buffer para el texto desencriptado
	decrypted := make([]byte, len(msg))

	// Desencriptar el mensaje
	mode.CryptBlocks(decrypted, msg)

	// Eliminar el padding PKCS7
	decrypted, err = pkcs7Unpadding(decrypted, aes.BlockSize)
	if err != nil {
		panic(err)
	}

	fmt.Println(string(decrypted))
}

// pkcs7Unpadding elimina el padding PKCS7
func pkcs7Unpadding(plaintext []byte, blockSize int) ([]byte, error) {
	length := len(plaintext)
	if length == 0 {
		return nil, fmt.Errorf("plaintext is empty")
	}
	if length%blockSize != 0 {
		return nil, fmt.Errorf("plaintext is not a multiple of the block size")
	}

	padding := int(plaintext[length-1])
	if padding > blockSize || padding == 0 {
		return nil, fmt.Errorf("invalid padding size")
	}

	for _, v := range plaintext[length-padding:] {
		if int(v) != padding {
			return nil, fmt.Errorf("invalid padding byte")
		}
	}

	return plaintext[:length-padding], nil
}
