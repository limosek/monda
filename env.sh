
MONDA=$(realpath $(dirname $0))
export PATH=$MONDA:$MONDA/bin:$PATH

if ! which php >/dev/null; then
    echo "PHP not installed? Monda will not work!"
fi

if ! which realpath >/dev/null; then
    echo "Realpath not installed? Monda will not work!"
fi

if ! which fdp >/dev/null; then
    echo "Graphviz not installed? Graphics output will not work!"
fi
