module.exports = {
  base: "/phabalicious/",
  title: "Phabalicious",
  description: "A deployment system and general purpose helper",
  theme: require.resolve("@factorial/vuepress-theme"),
  themeConfig: {
    repo: "factorial-io/phabalicious",
    editLinks: true,
    editLinkText: "Help us improve this page!",
    docsDir: "docs",
    sidebar: [
      "/guide.html",
      "/installation.html",
      "/usage.html",
      "/available-tasks.html",
      "/configuration.html",
      "/docker-integration.html",
      "/workspace.html",
      "/scripts.html",
      "/app-scaffold.html",
      "/app-create-destroy.html",
      "/deploying-artifacts.html",
      "/local-overrides.html",
      "/passwords.html",
      "/contribute.html",
      "/Changelog.html"
    ]
  }
};
