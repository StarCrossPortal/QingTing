import requests
from lxml import etree
import datetime

now = datetime.datetime.now()

class Dangdang(object):

    def __init__(self):
        self.headers = {
            "Host": "yzb.bupt.edu.cn",
            # "Connection": "keep-alive",
            # "Upgrade-Insecure-Requests": "1",
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.75 Safari/537.36",
            # "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9",
            # "Accept-Encoding": "gzip, deflate",
            # "Accept-Language": "zh-CN,zh;q=0.9"
        }


    def get_dangdang(self, page):
        # if page == 0:
        #     page = ''
        # 发送请求到当当网获取数据
        url = "https://yzb.bupt.edu.cn/list/list.php?p=8_4_%s" % page

        # 发送请求
        response = requests.get(url=url, headers=self.headers)
        response.encoding = "utf8"
        if response:
            # HTML数据的实例化
            html = etree.HTML(response.text)

            # print(response.text)
            # exit()
            items = html.xpath("//ul[@class='padlr20 ovhi']/li")
            # print(items)
            # exit()
            return items

    def join_list(self, item):
        # 处理列表到字符串
        return "".join(item)

    def parse_item(self, items):

        if items is None:
            return None

        # 适用于存放存储到mongodb之前的数据
        result_list = []
        for item in items:

            # 公告
            title = item.xpath(".//a/text()")
            # print(title)
            # 链接
            href = item.xpath(".//a/@href")
            # print(href)
            # exit()
            # 发布日期
            datetime = item.xpath(".//a/font/text()")

            url = self.join_list(href)


            if "../../" in url:
                url = url.replace('../../', 'https://yzb.bupt.edu.cn/')
                # print(2)

            if "http" not in url:
                url = "https://yzb.bupt.edu.cn/" + url
                # print(3)


            # exit()
            if "https://yzb.bupt.edu.cn" not in url:
                continue
            # exit()
            # print(url)
            response = requests.get(url=url, headers=self.headers, allow_redirects=False)

            response.encoding = "utf8"
            content = ""
            if response:
                # HTML数据的实例化
                html = etree.HTML(response.text)
                # print(len(html.xpath("//div[@class='zsxxxq_cont_cent']")))

                if (html is not None and len(html.xpath("//div[@class='content pad10']")) > 0):
                    content = html.xpath("//div[@class='content pad10']")[0]
                    content = etree.tostring(content, encoding='utf-8')
                    content = content.decode('utf8')
                    # print()
                    # exit()
                    # content = html.xpath("//div[@class='v_news_content']//text()")
            result_list.append(
                {
                    "title": self.join_list(title),
                    "release_time": self.join_list(datetime),
                    # "content": self.join_list(content),
                    "content": content,
                    "source_addr": url,
                    "school_name": "北京邮电大学",
                    "gather_time": now.strftime("%Y-%m-%d %H:%M:%S"),

                }
            )

        return result_list

    def insert_data(self, result_list):
        print(result_list)


def main():
    import json
    d = Dangdang()
    for page in range(0, 2):
        items = d.get_dangdang(page=page)
        result = d.parse_item(items=items)

        if result is None:
            continue

        if len(result) == 0:
            continue

        d.insert_data(result_list=result)


if __name__ == '__main__':
    main()
