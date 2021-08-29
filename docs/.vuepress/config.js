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
        title: 'Documentation',   // required
        path: '/guide.html',      // optional, link of the title, which should be an absolute path and must exist
        collapsable: false, // optional, defaults to true
        children: [
          "/guide.html",
          "/installation.html",
          "/usage.html",
          "/available-tasks.html",
          "/configuration.html",
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
          "/Changelog.html"
        ]
      },
      {
        title: 'Blog',   // required
        path: '/blog/',      // optional, link of the title, which should be an absolute path and must exist
        collapsable: false, // optional, defaults to true
        children: [
            "/blog/",
            "/blog/secrets-in-phab.html"
        ],
      }
    ]
  }
};
