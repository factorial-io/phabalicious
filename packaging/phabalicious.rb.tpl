class Phab < Formula
  desc " Supports your deployments and every-day devops-tasks "
  homepage "http://docs.phab.io"
  url "URL_PLACEHOLDER"
  sha256 "SHA_PLACEHOLDER"
  license "MIT"
  def install
    bin.install 'phabalicious.phar' => 'phab'
  end
end
