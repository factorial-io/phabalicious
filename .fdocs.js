const config = require("@factorial/docs/config");

module.exports = function (defaultConfig) {
    return config(defaultConfig, {
        projectName: "Phabalicious",
        input: "docs",
        output: "docs/_site",
        githubUrl: "https://github.com/factorial-io/phabalicious",
        twitter: "@phabalicious",
        openSource: true,
        heroImage: {
            src: "/assets/hero.png",
            width: 720,
            height: 600,
        },
        logo: {
            src: "/assets/logo.svg",
            width: 115,
            height: 30,
        },
        algolia: {
            appId: "BH4D9OD16A",
            apiKey: "69abc87124806b56252e00022c94f392",
            indexName: "phab",
        },
        menu: [
            {
                path: "documentation",
                children: [
                    "guide",
                    "installation",
                    "usage",
                    "configuration",
                    "commands",
                    "inheritance",
                    "docker-integration",
                    "workspace",
                    "scripts",
                    "scaffolder",
                    "app-scaffold",
                    "app-create-destroy",
                    "deploying-artifacts",
                    "kubernetes",
                    "offsite-backups",
                    "local-overrides",
                    "passwords",
                    "contribute",
                ],
            },
            {
                path: "blog",
                children: [
                    "blog/introduction",
                    "blog/architecture",
                    "blog/whats-new-in-phab-3-7",
                    "blog/how-to-use-secrets",
                    "blog/how-to-use-phab-beta-in-parallel",
                    "blog/whats-new-in-phab-3-8",
                ],
            },
            "changelog",
        ],
    });
};
