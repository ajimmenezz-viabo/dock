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
	"os"
)

func main() {

	pkBase64 := os.Args[1]
	aesBase64 := os.Args[2]
	ivBase64 := os.Args[3]
	msgBase64 := os.Args[4]

	pk, err := base64.StdEncoding.DecodeString(pkBase64)
	if err != nil {
		panic(err)
	}

	blockPEM, _ := pem.Decode(pk)
	if blockPEM == nil {
		panic(1)
	}

	privateKey, err := x509.ParsePKCS8PrivateKey(blockPEM.Bytes)
	if err != nil {
		panic(err)
	}

	pkPEM := privateKey.(*rsa.PrivateKey)

	aesKey, err := base64.StdEncoding.DecodeString(aesBase64)
	if err != nil {
		panic(err)
	}

	hash := sha256.New()

	key, err := rsa.DecryptOAEP(hash, rand.Reader, pkPEM, aesKey, nil)
	if err != nil {
		panic(err)
	}

	iv, err := base64.StdEncoding.DecodeString(ivBase64)
	if err != nil {
		panic(err)
	}

	msg, err := base64.StdEncoding.DecodeString(msgBase64)
	if err != nil {
		panic(err)
	}

	block, err := aes.NewCipher(key)
	if err != nil {
		panic(err)
	}

	aead, err := cipher.NewGCM(block)
	if err != nil {
		panic(err)
	}

	output, err := aead.Open(nil, iv, msg, nil)
	if err != nil {
		panic(err)
	}

	fmt.Println(string(output))
}
