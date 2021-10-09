module.exports = {
  base: "/",
  title: "Phabalicious",
  description: "A deployment system and general purpose helper",
  theme: require.resolve("@factorial/vuepress-theme"),
  themeConfig: {
    algolia: {
      apiKey: '69abc87124806b56252e00022c94f392',
      indexName: 'phab',
    },
    repo: "factorial-io/phabalicious",
    editLinks: true,
    editLinkText: "Help us improve this page!",
    docsDir: "docs",
    sidebar: [
      {
        title: 'Documentation',
        path: '/guide.html',
        sidebarDepth: 2,
        collapsable: false,
        children: [
          "/guide.html",
          "/installation.html",
          "/usage.html",
          "/configuration.html",
          "/commands.html",
          "/inheritance.html",
          "/docker-integration.html",
          "/workspace.html",
          "/scripts.html",
          "/scaffolder.html",
          "/app-scaffold.html",
          "/app-create-destroy.html",
          "/deploying-artifacts.html",
          "/kubernetes.html",
          "/offsite-backups.html",
          "/local-overrides.html",
          "/passwords.html",
          "/contribute.html",
        ]
      },
      {
        title: "Blog",
        path: "/blog/",
        collapsable: false,
        sidebarDepth: 3,
        children: [
            "/blog/architecture.html",
            "/blog/whats-new-in-phab-3-7.html",
            "/blog/how-to-use-secrets.html"
        ],
      },
      "/Changelog.html"
    ]
  }
};
